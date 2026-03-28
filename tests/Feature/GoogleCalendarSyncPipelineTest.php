<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\GoogleCalendarEventMap;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Services\GoogleCalendarSyncEventPublisher;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;

it('syncs google calendar outbox event successfully and persists appointment map', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
        'duration_minutes' => 45,
    ]);

    configureGoogleCalendarRuntime();

    app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'id' => 'gcal-event-0001',
            'updated' => '2026-03-03T13:00:00Z',
        ], 200),
    ]);

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = GoogleCalendarSyncEvent::query()->first();
    $map = GoogleCalendarEventMap::query()
        ->where('appointment_id', $appointment->id)
        ->first();
    $log = GoogleCalendarSyncLog::query()
        ->where('google_calendar_sync_event_id', $event?->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_SYNCED)
        ->and((int) $event?->attempts)->toBe(1)
        ->and($event?->processed_at)->not->toBeNull()
        ->and($map)->not->toBeNull()
        ->and($map?->google_event_id)->toBe('gcal-event-0001')
        ->and($map?->calendar_id)->toBe('crm-calendar@example.com')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(GoogleCalendarSyncEvent::STATUS_SYNCED);
});

it('fails fast when google calendar sync runtime is enabled but not fully configured', function (): void {
    configureGoogleCalendarRuntime();

    ClinicSetting::setValue('google_calendar.calendar_id', '', [
        'group' => 'google_calendar',
        'label' => 'Google Calendar ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    $this->artisan('google-calendar:sync-events')
        ->expectsOutputToContain('Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).')
        ->assertFailed();
});

it('does not enqueue google calendar outbox events when runtime is misconfigured', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(6),
        'duration_minutes' => 30,
    ]);

    configureGoogleCalendarRuntime();

    ClinicSetting::setValue('google_calendar.calendar_id', '', [
        'group' => 'google_calendar',
        'label' => 'Google Calendar ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->toBeNull()
        ->and(GoogleCalendarSyncEvent::query()->count())->toBe(0);
});

it('moves google calendar outbox event to dead letter after max attempts', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(3),
        'duration_minutes' => 30,
    ]);

    configureGoogleCalendarRuntime();

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    $event?->update([
        'max_attempts' => 1,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'error' => [
                'message' => 'remote error',
            ],
        ], 500),
    ]);

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();
    $log = GoogleCalendarSyncLog::query()
        ->where('google_calendar_sync_event_id', $event?->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_DEAD)
        ->and((int) $event?->attempts)->toBe(1)
        ->and((string) $event?->last_error)->toContain('remote error')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(GoogleCalendarSyncEvent::STATUS_FAILED);
});

it('fails strict exit and records dead-letter alert when google sync has dead letters', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(3),
        'duration_minutes' => 30,
    ]);

    configureGoogleCalendarRuntime();

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    $event?->update([
        'max_attempts' => 1,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'error' => [
                'message' => 'strict dead-letter',
            ],
        ], 500),
    ]);

    $this->artisan('google-calendar:sync-events', [
        '--strict-exit' => true,
    ])
        ->expectsOutputToContain('STRICT_EXIT_STATUS: FAIL')
        ->assertFailed();

    $event = $event?->fresh();

    $deadLetterAlert = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->get()
        ->first(fn (AuditLog $log): bool => data_get($log->metadata, 'channel') === 'sync_dead_letter_alert'
            && data_get($log->metadata, 'command') === 'google-calendar:sync-events');

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_DEAD)
        ->and($deadLetterAlert)->not->toBeNull()
        ->and((int) data_get($deadLetterAlert?->metadata, 'dead_backlog', 0))->toBeGreaterThanOrEqual(1);
});

it('publishes google calendar outbox event from appointment observer on create', function (): void {
    configureGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(2),
        'duration_minutes' => 30,
    ]);

    $event = GoogleCalendarSyncEvent::query()
        ->where('appointment_id', $appointment->id)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->event_type)->toBe(GoogleCalendarSyncEvent::EVENT_UPSERT)
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_PENDING);
});

it('publishes delete event from appointment observer on soft delete', function (): void {
    configureGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(6),
        'duration_minutes' => 30,
    ]);

    $appointment->delete();

    $deleteEvent = GoogleCalendarSyncEvent::query()
        ->where('appointment_id', $appointment->id)
        ->where('event_type', GoogleCalendarSyncEvent::EVENT_DELETE)
        ->latest('id')
        ->first();

    expect($deleteEvent)->not->toBeNull()
        ->and($deleteEvent?->status)->toBe(GoogleCalendarSyncEvent::STATUS_PENDING);
});

it('keeps deterministic idempotency key for repeated google publish attempts and requeues after synced', function (): void {
    configureGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(4),
        'duration_minutes' => 30,
    ]);

    $appointmentId = (int) $appointment->id;
    $tasks = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static fn (): ?int => app(GoogleCalendarSyncEventPublisher::class)
            ->publishForAppointmentId($appointmentId)?->id;
    }

    $resultIds = Concurrency::driver('sync')->run($tasks);

    $event = GoogleCalendarSyncEvent::query()
        ->where('appointment_id', $appointmentId)
        ->first();

    expect($event)->not->toBeNull()
        ->and(collect($resultIds)->filter()->unique()->count())->toBe(1)
        ->and(GoogleCalendarSyncEvent::query()->count())->toBe(1)
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_PENDING);

    $event?->markProcessing();
    $event?->markSynced('gcal-event-open-001', 200);

    $replayedEvent = app(GoogleCalendarSyncEventPublisher::class)
        ->publishForAppointmentId($appointmentId);

    expect($replayedEvent)->not->toBeNull()
        ->and((int) $replayedEvent?->id)->toBe((int) $event?->id)
        ->and($replayedEvent?->status)->toBe(GoogleCalendarSyncEvent::STATUS_PENDING)
        ->and((int) $replayedEvent?->attempts)->toBe(0);
});

it('reclaims stale processing google events and retries them successfully', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(5),
        'duration_minutes' => 30,
    ]);

    configureGoogleCalendarRuntime();

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    GoogleCalendarSyncEvent::runWithinManagedWorkflow(function () use ($event): void {
        $event?->forceFill([
            'status' => GoogleCalendarSyncEvent::STATUS_PROCESSING,
            'attempts' => 1,
            'locked_at' => now()->subMinutes(30),
            'next_retry_at' => now()->addMinutes(30),
            'last_error' => 'simulated worker crash',
        ])->save();
    });

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'id' => 'gcal-event-retry-0001',
            'updated' => '2026-03-04T03:00:00Z',
        ], 200),
    ]);

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_SYNCED)
        ->and((int) $event?->attempts)->toBe(2)
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toBe('');
});

it('moves stale processing google events to dead when max attempts already reached', function (): void {
    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(7),
        'duration_minutes' => 30,
    ]);

    configureGoogleCalendarRuntime();

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    GoogleCalendarSyncEvent::runWithinManagedWorkflow(function () use ($event): void {
        $event?->forceFill([
            'status' => GoogleCalendarSyncEvent::STATUS_PROCESSING,
            'attempts' => 3,
            'max_attempts' => 3,
            'locked_at' => now()->subMinutes(30),
            'next_retry_at' => now()->addMinutes(10),
            'last_error' => 'simulated worker crash',
        ])->save();
    });

    Http::preventStrayRequests();

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_DEAD)
        ->and((int) $event?->attempts)->toBe(3)
        ->and($event?->next_retry_at)->toBeNull()
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toContain('max attempts');

    Http::assertNothingSent();
});

it('falls back to create event when google upsert target event is stale', function (): void {
    configureGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(8),
        'duration_minutes' => 30,
    ]);

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    GoogleCalendarEventMap::query()->create([
        'appointment_id' => $appointment->id,
        'branch_id' => $appointment->branch_id,
        'calendar_id' => 'crm-calendar@example.com',
        'google_event_id' => 'stale-event-id',
        'payload_checksum' => $event?->payload_checksum,
        'last_event_id' => $event?->id,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events/stale-event-id' => Http::response([
            'error' => [
                'message' => 'Not Found',
            ],
        ], 404),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'id' => 'gcal-event-recreated-001',
            'updated' => '2026-03-04T01:00:00Z',
        ], 200),
    ]);

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();
    $map = GoogleCalendarEventMap::query()
        ->where('appointment_id', $appointment->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_SYNCED)
        ->and($map)->not->toBeNull()
        ->and($map?->google_event_id)->toBe('gcal-event-recreated-001');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/events/stale-event-id');
    });

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/events');
    });
});

it('fails sync when google returns success without event id on upsert', function (): void {
    configureGoogleCalendarRuntime();

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(10),
        'duration_minutes' => 30,
    ]);

    $event = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointment($appointment->fresh());

    expect($event)->not->toBeNull();

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'gcal-test-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*/events' => Http::response([
            'updated' => '2026-03-04T02:00:00Z',
            'message' => 'ok but missing id',
        ], 200),
    ]);

    $this->artisan('google-calendar:sync-events')
        ->assertSuccessful();

    $event = $event?->fresh();
    $log = GoogleCalendarSyncLog::query()
        ->where('google_calendar_sync_event_id', $event?->id)
        ->latest('id')
        ->first();
    $map = GoogleCalendarEventMap::query()
        ->where('appointment_id', $appointment->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(GoogleCalendarSyncEvent::STATUS_FAILED)
        ->and((string) $event?->last_error)->toContain('event id hợp lệ')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(GoogleCalendarSyncEvent::STATUS_FAILED)
        ->and($map)->toBeNull();
});

function configureGoogleCalendarRuntime(): void
{
    ClinicSetting::setValue('google_calendar.enabled', true, [
        'group' => 'google_calendar',
        'label' => 'Bật Google Calendar',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.client_id', 'gcal-client-id', [
        'group' => 'google_calendar',
        'label' => 'Google Client ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.client_secret', 'gcal-client-secret', [
        'group' => 'google_calendar',
        'label' => 'Google Client Secret',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.refresh_token', 'gcal-refresh-token', [
        'group' => 'google_calendar',
        'label' => 'Google Refresh Token',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.calendar_id', 'crm-calendar@example.com', [
        'group' => 'google_calendar',
        'label' => 'Google Calendar ID',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('google_calendar.sync_mode', 'one_way_to_google', [
        'group' => 'google_calendar',
        'label' => 'Chế độ đồng bộ',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);
}
