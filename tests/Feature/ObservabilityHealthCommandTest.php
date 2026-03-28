<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicSetting;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use Illuminate\Support\Carbon;
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
        'payload' => [
            'appointment_id' => $appointment->id,
            'branch_id' => $appointment->branch_id,
        ],
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

it('ignores unrelated automation failures outside observability control plane budget', function (): void {
    seedObservabilityBudgets(
        deadLetterBudget: 0,
        retryableFailureBudget: 0,
        openKpiAlertBudget: 0,
        snapshotSlaBudget: 0,
        recentAutomationFailureBudget: 0,
    );

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: null,
        metadata: [
            'command' => 'care:send-birthday-message',
            'channel' => 'birthday_automation',
            'status' => 'failed',
        ],
    );

    $this->artisan('ops:check-observability-health', [
        '--strict' => true,
        '--window-hours' => 1,
    ])
        ->expectsOutputToContain('OBS_RECENT_AUTOMATION_FAILURES: 0')
        ->expectsOutputToContain('OBS_HEALTH_STATUS: healthy')
        ->assertSuccessful();
});

it('counts tracked ops control plane failures in recent automation failure budget', function (): void {
    seedObservabilityBudgets(
        deadLetterBudget: 0,
        retryableFailureBudget: 0,
        openKpiAlertBudget: 0,
        snapshotSlaBudget: 0,
        recentAutomationFailureBudget: 0,
    );

    AuditLog::record(
        entityType: AuditLog::ENTITY_AUTOMATION,
        entityId: 0,
        action: AuditLog::ACTION_FAIL,
        actorId: null,
        metadata: [
            'command' => 'ops:create-backup-artifact',
            'status' => 'failed',
        ],
    );

    $this->artisan('ops:check-observability-health', [
        '--strict' => true,
        '--window-hours' => 1,
    ])
        ->expectsOutputToContain('OBS_RECENT_AUTOMATION_FAILURES: 1')
        ->expectsOutputToContain('recent_automation_failures')
        ->expectsOutputToContain('OBS_HEALTH_STATUS: unhealthy')
        ->assertFailed();
});

it('defaults observability snapshot date to yesterday for sla budget checks', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-28 02:25:00'));

    try {
        seedObservabilityBudgets(
            deadLetterBudget: 0,
            retryableFailureBudget: 0,
            openKpiAlertBudget: 0,
            snapshotSlaBudget: 0,
            recentAutomationFailureBudget: 100,
        );

        ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-27',
            'branch_id' => null,
            'branch_scope_id' => 0,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_ON_TIME,
            'generated_at' => Carbon::parse('2026-03-28 00:20:00'),
            'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
            'payload' => ['booking_count' => 1],
            'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:20:00')->toIso8601String()],
        ]);

        ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-28',
            'branch_id' => null,
            'branch_scope_id' => 0,
            'status' => ReportSnapshot::STATUS_FAILED,
            'sla_status' => ReportSnapshot::SLA_MISSING,
            'generated_at' => null,
            'sla_due_at' => Carbon::parse('2026-03-29 06:00:00'),
            'payload' => [],
            'lineage' => ['generated_at' => null],
        ]);

        $this->artisan('ops:check-observability-health', [
            '--strict' => true,
            '--window-hours' => 1,
        ])
            ->expectsOutputToContain('OBS_SNAPSHOT_DATE: 2026-03-27')
            ->expectsOutputToContain('OBS_SNAPSHOT_SLA_VIOLATIONS: 0')
            ->expectsOutputToContain('OBS_HEALTH_STATUS: healthy')
            ->assertSuccessful();
    } finally {
        Carbon::setTestNow();
    }
});

it('counts only open kpi alerts in the observability budget', function (): void {
    seedObservabilityBudgets(
        deadLetterBudget: 0,
        retryableFailureBudget: 0,
        openKpiAlertBudget: 1,
        snapshotSlaBudget: 10,
        recentAutomationFailureBudget: 100,
    );

    OperationalKpiAlert::factory()->create([
        'status' => OperationalKpiAlert::STATUS_NEW,
    ]);

    OperationalKpiAlert::factory()->create([
        'status' => OperationalKpiAlert::STATUS_ACK,
    ]);

    OperationalKpiAlert::factory()->create([
        'status' => OperationalKpiAlert::STATUS_RESOLVED,
    ]);

    $this->artisan('ops:check-observability-health', [
        '--strict' => true,
        '--window-hours' => 1,
    ])
        ->expectsOutputToContain('OBS_OPEN_KPI_ALERTS: 2')
        ->expectsOutputToContain('open_kpi_alerts')
        ->expectsOutputToContain('OBS_HEALTH_STATUS: unhealthy')
        ->assertFailed();
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
