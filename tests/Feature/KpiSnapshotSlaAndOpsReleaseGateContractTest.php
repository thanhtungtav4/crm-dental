<?php

/**
 * RRB-015 — KPI snapshot SLA consistency, ZNS dead-letter threshold,
 *            and OPS release gate catalog contract.
 *
 * Covers:
 *  - ReportSnapshotSlaService::resolveSlaStatus() classifies on_time / late / stale / missing correctly
 *  - SLA boundary: generated_at > sla_due_at → late; generated_at < stale_cutoff (today snapshot) → stale
 *  - ZNS automation dead count increases when seeded events have STATUS_DEAD
 *  - ZNS automationDeadCount is scoped by event_type
 *  - OpsReleaseGateCatalog::steps('ci') returns non-empty gate list with required keys
 *  - OpsReleaseGateCatalog::requiredProductionCommands() includes base gate commands
 */

use App\Models\ReportSnapshot;
use App\Models\ZnsAutomationEvent;
use App\Services\ReportSnapshotSlaService;
use App\Services\ZnsOperationalReadModelService;
use App\Support\OpsReleaseGateCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// SLA classification contracts
// ---------------------------------------------------------------------------

it('resolveSlaStatus returns on_time when generated_at is within sla_due_at and not stale', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $snapshot = new ReportSnapshot([
        'generated_at' => now()->subMinutes(5),
        'sla_due_at' => now()->addHour(),
        'snapshot_date' => today()->subDay(), // not today → stale rule doesn't apply
    ]);

    $staleCutoff = now()->subHours(24);
    $status = $svc->resolveSlaStatus($snapshot, $staleCutoff);

    expect($status)->toBe(ReportSnapshot::SLA_ON_TIME);
});

it('resolveSlaStatus returns late when generated_at exceeds sla_due_at', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $snapshot = new ReportSnapshot([
        'generated_at' => now()->subMinutes(30),
        'sla_due_at' => now()->subHour(), // due 1h ago, generated 30min ago → late
        'snapshot_date' => today()->subDay(),
    ]);

    $staleCutoff = now()->subHours(24);
    $status = $svc->resolveSlaStatus($snapshot, $staleCutoff);

    expect($status)->toBe(ReportSnapshot::SLA_LATE);
});

it('resolveSlaStatus returns stale when today snapshot generated_at is older than stale cutoff', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $snapshot = new ReportSnapshot([
        'generated_at' => now()->subHours(30), // generated 30h ago
        'sla_due_at' => now()->addHour(),     // still within SLA window
        'snapshot_date' => today(),             // today snapshot
    ]);

    $staleCutoff = now()->subHours(24); // anything older than 24h is stale

    $status = $svc->resolveSlaStatus($snapshot, $staleCutoff);

    expect($status)->toBe(ReportSnapshot::SLA_STALE);
});

it('resolveSlaStatus returns missing when generated_at is null', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $snapshot = new ReportSnapshot([
        'generated_at' => null,
        'sla_due_at' => now()->addHour(),
        'snapshot_date' => today()->subDay(),
    ]);

    $staleCutoff = now()->subHours(24);
    $status = $svc->resolveSlaStatus($snapshot, $staleCutoff);

    expect($status)->toBe(ReportSnapshot::SLA_MISSING);
});

it('checkScope returns missing=1 and creates placeholder when no snapshot exists', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $result = $svc->checkScope(
        snapshotKey: 'daily_revenue',
        snapshotDate: Carbon::yesterday(),
        branchId: null,
        dryRun: false,
    );

    expect($result['missing'])->toBe(1)
        ->and($result['on_time'] + $result['late'] + $result['stale'])->toBe(0);

    expect(ReportSnapshot::query()
        ->where('snapshot_key', 'daily_revenue')
        ->where('sla_status', ReportSnapshot::SLA_MISSING)
        ->exists()
    )->toBeTrue();
});

it('checkScope dry-run does not persist placeholder snapshot', function (): void {
    $svc = app(ReportSnapshotSlaService::class);

    $svc->checkScope(
        snapshotKey: 'daily_revenue_dry',
        snapshotDate: Carbon::yesterday(),
        branchId: null,
        dryRun: true,
    );

    expect(ReportSnapshot::query()
        ->where('snapshot_key', 'daily_revenue_dry')
        ->exists()
    )->toBeFalse();
});

// ---------------------------------------------------------------------------
// ZNS dead-letter threshold
// ---------------------------------------------------------------------------

it('zns automationDeadCount increases when STATUS_DEAD events are seeded', function (): void {
    $svc = app(ZnsOperationalReadModelService::class);

    $before = $svc->automationDeadCount();

    ZnsAutomationEvent::query()->insert([
        'event_key' => 'dead-zns-'.uniqid(),
        'event_type' => 'appointment_reminder',
        'template_key' => 'appt_reminder',
        'template_id_snapshot' => 'tpl-001',
        'phone' => '0901234567',
        'normalized_phone' => '84901234567',
        'payload' => json_encode(['type' => 'test']),
        'payload_checksum' => sha1('test-zns-dead-'.uniqid()),
        'status' => ZnsAutomationEvent::STATUS_DEAD,
        'attempts' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($svc->automationDeadCount())->toBe($before + 1);
});

it('zns automationDeadCount is scoped to a specific event_type', function (): void {
    $svc = app(ZnsOperationalReadModelService::class);

    ZnsAutomationEvent::query()->insert([
        [
            'event_key' => 'dead-appt-'.uniqid(),
            'event_type' => 'appointment_reminder',
            'template_key' => 'appt_reminder',
            'template_id_snapshot' => 'tpl-001',
            'phone' => '0901234567',
            'normalized_phone' => '84901234567',
            'payload' => json_encode(['type' => 'appt']),
            'payload_checksum' => sha1('appt-dead-'.uniqid()),
            'status' => ZnsAutomationEvent::STATUS_DEAD,
            'attempts' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'event_key' => 'dead-care-'.uniqid(),
            'event_type' => 'care_followup',
            'template_key' => 'care_followup',
            'template_id_snapshot' => 'tpl-002',
            'phone' => '0901234568',
            'normalized_phone' => '84901234568',
            'payload' => json_encode(['type' => 'care']),
            'payload_checksum' => sha1('care-dead-'.uniqid()),
            'status' => ZnsAutomationEvent::STATUS_DEAD,
            'attempts' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $apptCount = $svc->automationDeadCount('appointment_reminder');
    $careCount = $svc->automationDeadCount('care_followup');
    $totalCount = $svc->automationDeadCount();

    expect($apptCount)->toBeGreaterThanOrEqual(1)
        ->and($careCount)->toBeGreaterThanOrEqual(1)
        ->and($totalCount)->toBeGreaterThanOrEqual($apptCount + $careCount);
});

it('zns automationDeadCount returns 0 for an unused event_type', function (): void {
    $svc = app(ZnsOperationalReadModelService::class);

    expect($svc->automationDeadCount('non_existent_event_type_x99'))->toBe(0);
});

// ---------------------------------------------------------------------------
// OPS release gate catalog contract
// ---------------------------------------------------------------------------

it('OpsReleaseGateCatalog ci steps returns non-empty gate list with required structure', function (): void {
    $steps = OpsReleaseGateCatalog::steps(
        profile: 'ci',
        withFinance: false,
    );

    expect($steps)->not->toBeEmpty();

    foreach ($steps as $step) {
        expect($step)->toHaveKey('name')
            ->toHaveKey('command')
            ->toHaveKey('arguments');
        expect(filled($step['name']))->toBeTrue();
        expect(filled($step['command']))->toBeTrue();
    }
});

it('OpsReleaseGateCatalog production steps include all base gate commands', function (): void {
    $commands = OpsReleaseGateCatalog::requiredProductionCommands(withFinance: false);

    expect($commands)->not->toBeEmpty();

    // base steps always present in every profile
    $baseCommands = [
        'schema:assert-no-pending-migrations',
        'schema:assert-critical-foreign-keys',
        'security:assert-action-permission-baseline',
    ];

    foreach ($baseCommands as $cmd) {
        expect(in_array($cmd, $commands, true))->toBeTrue(
            "Expected command [{$cmd}] to be in production gate catalog"
        );
    }
});

it('OpsReleaseGateCatalog production steps include automation actor and backup health gates', function (): void {
    $commands = OpsReleaseGateCatalog::requiredProductionCommands(withFinance: false);

    expect(in_array('security:check-automation-actor', $commands, true))->toBeTrue();
    expect(in_array('ops:check-backup-health', $commands, true))->toBeTrue();
});
