<?php

use App\Models\Branch;
use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\OperationalKpiAlertReadModelService;
use Illuminate\Support\Carbon;

it('builds scoped open and resolved alert summaries for ops surfaces', function (): void {
    $branchA = Branch::factory()->create(['name' => 'Q1']);
    $branchB = Branch::factory()->create(['name' => 'CG']);

    $ownerA = User::factory()->create([
        'branch_id' => $branchA->id,
        'email' => 'manager.q1@example.test',
    ]);
    $ownerB = User::factory()->create([
        'branch_id' => $branchB->id,
        'email' => 'manager.cg@example.test',
    ]);

    $snapshotA = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
    ]);

    $snapshotB = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:20:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:20:00')->toIso8601String()],
    ]);

    OperationalKpiAlert::query()->create([
        'snapshot_id' => $snapshotA->id,
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'owner_user_id' => $ownerA->id,
        'metric_key' => 'chair_utilization_rate',
        'threshold_direction' => 'min',
        'threshold_value' => 50,
        'observed_value' => 32,
        'severity' => 'high',
        'status' => OperationalKpiAlert::STATUS_NEW,
        'title' => 'Chair utilization dưới ngưỡng',
        'message' => 'Alert A1',
        'metadata' => [],
    ]);

    OperationalKpiAlert::query()->create([
        'snapshot_id' => $snapshotA->id,
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'owner_user_id' => $ownerA->id,
        'metric_key' => 'treatment_acceptance_rate',
        'threshold_direction' => 'min',
        'threshold_value' => 60,
        'observed_value' => 41,
        'severity' => 'medium',
        'status' => OperationalKpiAlert::STATUS_ACK,
        'title' => 'Treatment acceptance dưới ngưỡng',
        'message' => 'Alert A2',
        'metadata' => [],
    ]);

    OperationalKpiAlert::query()->create([
        'snapshot_id' => $snapshotA->id,
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchA->id,
        'owner_user_id' => $ownerA->id,
        'metric_key' => 'no_show_rate',
        'threshold_direction' => 'max',
        'threshold_value' => 15,
        'observed_value' => 22,
        'severity' => 'low',
        'status' => OperationalKpiAlert::STATUS_RESOLVED,
        'title' => 'No-show vượt ngưỡng',
        'message' => 'Alert A3',
        'metadata' => [],
        'resolved_by' => $ownerA->id,
        'resolved_at' => Carbon::parse('2026-03-28 00:40:00'),
        'resolution_note' => 'Handled',
    ]);

    OperationalKpiAlert::query()->create([
        'snapshot_id' => $snapshotB->id,
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branchB->id,
        'owner_user_id' => $ownerB->id,
        'metric_key' => 'chair_utilization_rate',
        'threshold_direction' => 'min',
        'threshold_value' => 55,
        'observed_value' => 30,
        'severity' => 'high',
        'status' => OperationalKpiAlert::STATUS_NEW,
        'title' => 'Chair utilization branch B',
        'message' => 'Alert B1',
        'metadata' => [],
    ]);

    $service = app(OperationalKpiAlertReadModelService::class);
    $summary = $service->opsSummaryForDate('2026-03-27', [$branchA->id]);

    expect($service->activeAlertCountForSnapshot($snapshotA->id))->toBe(2)
        ->and($service->openAlertCount())->toBe(3)
        ->and($service->openAlertCount([$branchA->id], '2026-03-27'))->toBe(2)
        ->and($service->resolvedAlertCount('2026-03-27', [$branchA->id]))->toBe(1)
        ->and($summary['open_count'])->toBe(2)
        ->and($summary['resolved_count'])->toBe(1)
        ->and($summary['open_alerts'])->toHaveCount(2)
        ->and(collect($summary['open_alerts'])->pluck('title')->all())->toBe([
            'Treatment acceptance dưới ngưỡng',
            'Chair utilization dưới ngưỡng',
        ])
        ->and($summary['open_alerts'][0]['branch'])->toBe('Q1')
        ->and($summary['open_alerts'][0]['owner'])->toBe('manager.q1@example.test');
});

it('returns empty alert summaries when the visible branch scope is empty', function (): void {
    $service = app(OperationalKpiAlertReadModelService::class);

    expect($service->openAlertCount([]))->toBe(0)
        ->and($service->resolvedAlertCount('2026-03-27', []))->toBe(0)
        ->and($service->opsSummaryForDate('2026-03-27', []))->toBe([
            'open_count' => 0,
            'resolved_count' => 0,
            'open_alerts' => [],
        ]);
});
