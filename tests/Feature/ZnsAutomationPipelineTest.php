<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Services\ZnsAutomationEventPublisher;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('publishes lead welcome zns event from web lead ingestion and syncs successfully', function (): void {
    $branch = Branch::factory()->create([
        'code' => 'BR-ZNS-WEB-LEAD',
        'active' => true,
    ]);

    configureWebLeadApiForZns(
        enabled: true,
        token: 'web-token-zns',
        defaultBranchCode: $branch->code,
    );
    configureZnsAutomationRuntime(
        autoLeadWelcome: true,
        templateLeadWelcome: 'tpl_lead_welcome_001',
    );

    $requestId = (string) Str::uuid();

    $this->postJson('/api/v1/web-leads', [
        'full_name' => 'Lead ZNS',
        'phone' => '0907777888',
        'branch_code' => $branch->code,
    ], [
        'Authorization' => 'Bearer web-token-zns',
        'X-Idempotency-Key' => $requestId,
    ])->assertCreated();

    $event = ZnsAutomationEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event?->event_type)->toBe(ZnsAutomationEvent::EVENT_LEAD_WELCOME)
        ->and($event?->status)->toBe(ZnsAutomationEvent::STATUS_PENDING)
        ->and((string) data_get($event?->payload, 'lead_request_id'))->toBe($requestId);

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-msg-001'],
        ], 200),
    ]);

    $this->artisan('zns:sync-automation-events')
        ->assertSuccessful();

    $event = $event?->fresh();
    $log = ZnsAutomationLog::query()
        ->where('zns_automation_event_id', $event?->id)
        ->latest('id')
        ->first();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(ZnsAutomationEvent::STATUS_SENT)
        ->and((int) $event?->attempts)->toBe(1)
        ->and($event?->provider_message_id)->toBe('zns-msg-001')
        ->and($log)->not->toBeNull()
        ->and($log?->status)->toBe(ZnsAutomationEvent::STATUS_SENT);
});

it('keeps deterministic idempotency key for concurrent appointment reminder publish attempts', function (): void {
    configureZnsAutomationRuntime(
        autoAppointmentReminder: true,
        templateAppointment: 'tpl_appointment_001',
    );

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(26),
        'reminder_hours' => 24,
    ]);

    $existingEvent = ZnsAutomationEvent::query()
        ->where('appointment_id', $appointment->id)
        ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
        ->first();

    expect($existingEvent)->not->toBeNull();

    $appointmentId = (int) $appointment->id;
    $tasks = [];

    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static fn (): ?int => app(ZnsAutomationEventPublisher::class)
            ->publishAppointmentReminder(Appointment::query()->findOrFail($appointmentId))?->id;
    }

    $resultIds = Concurrency::driver('sync')->run($tasks);

    $event = ZnsAutomationEvent::query()
        ->where('appointment_id', $appointmentId)
        ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
        ->first();

    expect($event)->not->toBeNull()
        ->and(collect($resultIds)->filter()->unique()->count())->toBe(1)
        ->and((int) $event?->id)->toBe((int) $existingEvent?->id)
        ->and(ZnsAutomationEvent::query()
            ->where('appointment_id', $appointmentId)
            ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
            ->count())->toBe(1);
});

it('reclaims stale processing zns automation events and retries successfully', function (): void {
    configureZnsAutomationRuntime(
        autoAppointmentReminder: true,
        templateAppointment: 'tpl_appointment_002',
    );

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHour(),
        'reminder_hours' => 2,
    ]);

    $event = ZnsAutomationEvent::query()
        ->where('appointment_id', $appointment->id)
        ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
        ->firstOrFail();

    $event->forceFill([
        'status' => ZnsAutomationEvent::STATUS_PROCESSING,
        'attempts' => 1,
        'locked_at' => now()->subMinutes(30),
        'next_retry_at' => now()->addMinutes(20),
        'last_error' => 'simulated worker crash',
    ])->save();

    Http::fake([
        'https://business.openapi.zalo.me/*' => Http::response([
            'error' => 0,
            'message' => 'Success',
            'data' => ['msg_id' => 'zns-msg-retry-001'],
        ], 200),
    ]);

    $this->artisan('zns:sync-automation-events')
        ->assertSuccessful();

    $event = $event->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(ZnsAutomationEvent::STATUS_SENT)
        ->and((int) $event?->attempts)->toBe(2)
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toBe('');
});

it('moves stale processing zns automation events to dead when max attempts already reached', function (): void {
    configureZnsAutomationRuntime(
        autoAppointmentReminder: true,
        templateAppointment: 'tpl_appointment_003',
    );

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addHours(3),
        'reminder_hours' => 2,
    ]);

    $event = ZnsAutomationEvent::query()
        ->where('appointment_id', $appointment->id)
        ->where('event_type', ZnsAutomationEvent::EVENT_APPOINTMENT_REMINDER)
        ->firstOrFail();

    $event->forceFill([
        'status' => ZnsAutomationEvent::STATUS_PROCESSING,
        'attempts' => 2,
        'max_attempts' => 2,
        'locked_at' => now()->subMinutes(30),
        'next_retry_at' => now()->addMinutes(10),
        'last_error' => 'simulated worker crash',
    ])->save();

    Http::preventStrayRequests();

    $this->artisan('zns:sync-automation-events')
        ->assertSuccessful();

    $event = $event->fresh();

    expect($event)->not->toBeNull()
        ->and($event?->status)->toBe(ZnsAutomationEvent::STATUS_DEAD)
        ->and((int) $event?->attempts)->toBe(2)
        ->and($event?->next_retry_at)->toBeNull()
        ->and($event?->locked_at)->toBeNull()
        ->and((string) ($event?->last_error ?? ''))->toContain('max attempts');

    Http::assertNothingSent();
});

it('publishes birthday greeting event from birthday automation and deduplicates by year', function (): void {
    configureZnsAutomationRuntime(
        autoBirthday: true,
        templateBirthday: 'tpl_birthday_001',
    );

    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'phone' => '0906666777',
        'birthday' => now()->toDateString(),
    ]);

    $this->artisan('care:generate-birthday-tickets', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $this->artisan('care:generate-birthday-tickets', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    expect(ZnsAutomationEvent::query()
        ->where('patient_id', $patient->id)
        ->where('event_type', ZnsAutomationEvent::EVENT_BIRTHDAY_GREETING)
        ->count())->toBe(1);
});

function configureWebLeadApiForZns(
    bool $enabled,
    string $token,
    ?string $defaultBranchCode = null,
): void {
    ClinicSetting::setValue('web_lead.enabled', $enabled, [
        'group' => 'web_lead',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('web_lead.api_token', $token, [
        'group' => 'web_lead',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('web_lead.default_branch_code', $defaultBranchCode ?? '', [
        'group' => 'web_lead',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('web_lead.rate_limit_per_minute', 120, [
        'group' => 'web_lead',
        'value_type' => 'integer',
    ]);
}

function configureZnsAutomationRuntime(
    bool $autoLeadWelcome = false,
    bool $autoAppointmentReminder = false,
    bool $autoBirthday = false,
    string $templateLeadWelcome = '',
    string $templateAppointment = '',
    string $templateBirthday = '',
): void {
    ClinicSetting::setValue('zns.enabled', true, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.access_token', 'zns-access-token', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.refresh_token', 'zns-refresh-token', [
        'group' => 'zns',
        'value_type' => 'text',
        'is_secret' => true,
    ]);

    ClinicSetting::setValue('zns.send_endpoint', 'https://business.openapi.zalo.me/message/template', [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.request_timeout_seconds', 15, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('zns.auto_send_lead_welcome', $autoLeadWelcome, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.auto_send_appointment_reminder', $autoAppointmentReminder, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.auto_send_birthday', $autoBirthday, [
        'group' => 'zns',
        'value_type' => 'boolean',
    ]);

    ClinicSetting::setValue('zns.appointment_reminder_default_hours', 24, [
        'group' => 'zns',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('zns.template_lead_welcome', $templateLeadWelcome, [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.template_appointment', $templateAppointment, [
        'group' => 'zns',
        'value_type' => 'text',
    ]);

    ClinicSetting::setValue('zns.template_birthday', $templateBirthday, [
        'group' => 'zns',
        'value_type' => 'text',
    ]);
}
