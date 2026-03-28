<?php

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\EmrSyncEvent;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\Patient;
use App\Services\EmrSyncEventPublisher;
use App\Services\GoogleCalendarSyncEventPublisher;
use Illuminate\Support\Facades\Concurrency;

it('keeps deterministic idempotency under concurrent google and emr publish attempts', function (): void {
    configureCrossProviderGoogleCalendarRuntime();
    configureCrossProviderEmrRuntime();

    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
        'duration_minutes' => 45,
    ]);

    $appointmentId = (int) $appointment->id;
    $patientId = (int) $patient->id;

    $tasks = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static fn (): array => [
            'channel' => 'google',
            'id' => app(GoogleCalendarSyncEventPublisher::class)->publishForAppointmentId($appointmentId)?->id,
        ];
        $tasks[] = static fn (): array => [
            'channel' => 'emr',
            'id' => app(EmrSyncEventPublisher::class)->publishForPatientId($patientId, 'manual.sync')?->id,
        ];
    }

    $resultRows = collect(Concurrency::driver('sync')->run($tasks));
    $googleResultIds = $resultRows->where('channel', 'google')->pluck('id')->filter();
    $emrResultIds = $resultRows->where('channel', 'emr')->pluck('id')->filter();

    $googleEvent = GoogleCalendarSyncEvent::query()
        ->where('appointment_id', $appointmentId)
        ->first();
    $emrEvent = EmrSyncEvent::query()
        ->where('patient_id', $patientId)
        ->where('event_type', 'manual.sync')
        ->first();

    expect($googleEvent)->not->toBeNull()
        ->and($emrEvent)->not->toBeNull()
        ->and($googleResultIds->unique()->count())->toBe(1)
        ->and($emrResultIds->unique()->count())->toBe(1)
        ->and(GoogleCalendarSyncEvent::query()->where('appointment_id', $appointmentId)->count())->toBe(1)
        ->and(EmrSyncEvent::query()->where('patient_id', $patientId)->where('event_type', 'manual.sync')->count())->toBe(1);

    $googleEvent?->markProcessing();
    $emrEvent?->markProcessing();
    $googleEvent?->markSynced('gcal-event-concurrency-001', 200);
    $emrEvent?->markSynced('emr-patient-concurrency-001', 200);

    $replayedGoogle = app(GoogleCalendarSyncEventPublisher::class)->publishForAppointmentId($appointmentId);
    $replayedEmr = app(EmrSyncEventPublisher::class)->publishForPatientId($patientId, 'manual.sync');

    expect($replayedGoogle)->not->toBeNull()
        ->and((int) $replayedGoogle?->id)->toBe((int) $googleEvent?->id)
        ->and($replayedGoogle?->status)->toBe(GoogleCalendarSyncEvent::STATUS_PENDING)
        ->and((int) $replayedGoogle?->attempts)->toBe(0)
        ->and($replayedEmr)->not->toBeNull()
        ->and((int) $replayedEmr?->id)->toBe((int) $emrEvent?->id)
        ->and($replayedEmr?->status)->toBe(EmrSyncEvent::STATUS_PENDING)
        ->and((int) $replayedEmr?->attempts)->toBe(0);
});

function configureCrossProviderGoogleCalendarRuntime(): void
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

function configureCrossProviderEmrRuntime(): void
{
    ClinicSetting::setValue('emr.enabled', true, [
        'group' => 'emr',
        'label' => 'Bật EMR',
        'value_type' => 'boolean',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.provider', 'external', [
        'group' => 'emr',
        'label' => 'Nhà cung cấp EMR',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.base_url', 'https://emr.example.test', [
        'group' => 'emr',
        'label' => 'EMR Base URL',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.api_key', 'test-api-key', [
        'group' => 'emr',
        'label' => 'EMR API Key',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    ClinicSetting::setValue('emr.clinic_code', 'CLINIC-HQ', [
        'group' => 'emr',
        'label' => 'Mã cơ sở',
        'value_type' => 'text',
        'is_secret' => false,
        'is_active' => true,
    ]);
}
