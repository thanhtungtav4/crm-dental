<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Services\OperationalKpiSnapshotReadModelService;
use Illuminate\Support\Carbon;

it('returns latest snapshot date with visible-branch fallback and snapshot counts', function (): void {
    Carbon::setTestNow('2026-03-28 09:00:00');

    try {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $branchC = Branch::factory()->create();

        ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-27',
            'branch_id' => $branchA->id,
            'branch_scope_id' => $branchA->id,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_ON_TIME,
            'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
            'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
            'payload' => ['no_show_rate' => 10],
            'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
        ]);

        ReportSnapshot::query()->create([
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => '2026-03-27',
            'branch_id' => $branchB->id,
            'branch_scope_id' => $branchB->id,
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_LATE,
            'generated_at' => Carbon::parse('2026-03-28 00:25:00'),
            'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
            'payload' => ['no_show_rate' => 20],
            'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:25:00')->toIso8601String()],
        ]);

        $service = app(OperationalKpiSnapshotReadModelService::class);
        $visibleBranchIds = [$branchA->id, $branchB->id, $branchC->id];

        $snapshotDate = $service->latestSnapshotDate($visibleBranchIds);
        $snapshots = $service->snapshotsForDate($snapshotDate, $visibleBranchIds, ['id', 'branch_scope_id', 'sla_status']);
        $counts = $service->snapshotCounts($snapshots, $visibleBranchIds);
        $cards = collect($service->renderedSnapshotCountCards($counts))->keyBy('key');

        expect($snapshotDate)->toBe('2026-03-27')
            ->and($counts)->toBe([
                'on_time' => 1,
                'late' => 1,
                'stale' => 0,
                'missing' => 1,
            ])
            ->and($cards->get('on_time'))->toMatchArray([
                'label' => 'On Time',
                'value' => 1,
                'badge_label' => '1',
            ])
            ->and($cards->get('missing'))->toMatchArray([
                'label' => 'Missing',
                'value' => 1,
                'badge_label' => '1',
            ])
            ->and($service->latestSnapshotDate([]))->toBe('2026-03-27');
    } finally {
        Carbon::setTestNow();
    }
});

it('returns the latest filtered snapshot for the selected branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-26',
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-27 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-27 06:00:00'),
        'payload' => ['booking_to_visit_rate' => 11.1],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-27 00:15:00')->toIso8601String()],
    ]);

    $expectedSnapshot = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_LATE,
        'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => ['booking_to_visit_rate' => 22.2],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_LATE,
        'generated_at' => Carbon::parse('2026-03-28 00:30:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => ['booking_to_visit_rate' => 88.8],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:30:00')->toIso8601String()],
    ]);

    $snapshot = app(OperationalKpiSnapshotReadModelService::class)->latestSnapshot(
        branchIds: [$branchA->id],
        filters: [
            'status' => ReportSnapshot::STATUS_SUCCESS,
            'sla_status' => ReportSnapshot::SLA_LATE,
        ],
    );

    expect($snapshot)->not->toBeNull()
        ->and($snapshot?->is($expectedSnapshot))->toBeTrue();
});

it('builds branch benchmark summary from visible peer snapshots', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $currentSnapshot = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [
            'no_show_rate' => 10,
            'treatment_acceptance_rate' => 80,
            'chair_utilization_rate' => 60,
        ],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:25:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [
            'no_show_rate' => 30,
            'treatment_acceptance_rate' => 50,
            'chair_utilization_rate' => 40,
        ],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:25:00')->toIso8601String()],
    ]);

    $summary = app(OperationalKpiSnapshotReadModelService::class)->branchBenchmarkSummary(
        snapshot: $currentSnapshot,
        branchIds: [$branchA->id, $branchB->id],
    );

    expect($summary)->toBe([
        'no_show_delta' => -10.0,
        'acceptance_delta' => 15.0,
        'chair_delta' => 10.0,
    ]);
});

it('counts sla violations for the selected snapshot date and branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_STALE,
        'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_FAILED,
        'sla_status' => ReportSnapshot::SLA_MISSING,
        'generated_at' => null,
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [],
        'lineage' => ['generated_at' => null],
    ]);

    $service = app(OperationalKpiSnapshotReadModelService::class);

    expect($service->slaViolationCountForDate('2026-03-27', [$branchA->id]))->toBe(1)
        ->and($service->slaViolationCountForDate('2026-03-27'))->toBe(2)
        ->and($service->slaViolationCountForDate('2026-03-27', []))->toBe(0);
});
