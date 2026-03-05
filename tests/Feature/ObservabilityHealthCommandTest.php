<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\GoogleCalendarSyncEvent;
use Illuminate\Support\Str;

it('fails strict mode when dead-letter error budget is exceeded', function (): void {
    seedObservabilityBudgets(
        deadLetterBudget: 0,
        retryableFailureBudget: 50,
        openKpiAlertBudget: 50,
        snapshotSlaBudget: 10,
        recentAutomationFailureBudget: 100,
    );

    $appointment = Appointment::factory()->create([
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->addDay(),
        'duration_minutes' => 30,
    ]);

    GoogleCalendarSyncEvent::query()->create([
        'event_key' => 'obs-dead-'.Str::uuid(),
        'appointment_id' => $appointment->id,
        'branch_id' => $appointment->branch_id,
        'event_type' => GoogleCalendarSyncEvent::EVENT_UPSERT,
        'payload' => [],
        'payload_checksum' => hash('sha256', 'obs-dead-'.$appointment->id),
        'status' => GoogleCalendarSyncEvent::STATUS_DEAD,
        'attempts' => 1,
        'max_attempts' => 1,
        'next_retry_at' => null,
        'locked_at' => null,
        'processed_at' => now(),
    ]);

    $this->artisan('ops:check-observability-health', [
        '--strict' => true,
        '--window-hours' => 1,
    ])
        ->expectsOutputToContain('OBS_HEALTH_STATUS: unhealthy')
        ->expectsOutputToContain('dead_backlog_total')
        ->expectsOutputToContain('Strict mode: observability health vượt error budget.')
        ->assertFailed();

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
        ->where('action', AuditLog::ACTION_FAIL)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and(data_get($audit?->metadata, 'channel'))->toBe('observability_health')
        ->and((string) data_get($audit?->metadata, 'status'))->toBe('unhealthy');
});

it('passes strict mode when all observability metrics are within budget', function (): void {
    seedObservabilityBudgets(
        deadLetterBudget: 0,
        retryableFailureBudget: 0,
        openKpiAlertBudget: 0,
        snapshotSlaBudget: 0,
        recentAutomationFailureBudget: 100,
    );

    $this->artisan('ops:check-observability-health', [
        '--strict' => true,
        '--window-hours' => 1,
    ])
        ->expectsOutputToContain('OBS_HEALTH_STATUS: healthy')
        ->assertSuccessful();
});

function seedObservabilityBudgets(
    int $deadLetterBudget,
    int $retryableFailureBudget,
    int $openKpiAlertBudget,
    int $snapshotSlaBudget,
    int $recentAutomationFailureBudget,
): void {
    ClinicSetting::setValue('observability.dead_letter_error_budget', $deadLetterBudget, [
        'group' => 'ops',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('observability.retryable_failure_error_budget', $retryableFailureBudget, [
        'group' => 'ops',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('observability.open_kpi_alert_error_budget', $openKpiAlertBudget, [
        'group' => 'ops',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('observability.snapshot_sla_error_budget', $snapshotSlaBudget, [
        'group' => 'ops',
        'value_type' => 'integer',
    ]);

    ClinicSetting::setValue('observability.recent_automation_failure_error_budget', $recentAutomationFailureBudget, [
        'group' => 'ops',
        'value_type' => 'integer',
    ]);
}
