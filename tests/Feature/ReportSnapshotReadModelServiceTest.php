<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Services\ReportSnapshotReadModelService;
use Illuminate\Support\Carbon;

it('finds snapshots for a date and resolves the latest successful snapshot by branch scope', function (): void {
    $branch = Branch::factory()->create();

    $expected = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branch->id,
        'branch_scope_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:25:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => ['booking_count' => 2],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:25:00')->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => null,
        'branch_scope_id' => 0,
        'status' => ReportSnapshot::STATUS_FAILED,
        'sla_status' => ReportSnapshot::SLA_MISSING,
        'generated_at' => null,
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => [],
        'lineage' => ['generated_at' => null],
    ]);

    $service = app(ReportSnapshotReadModelService::class);

    expect($service->snapshotsForDate('operational_kpi_pack', '2026-03-27', $branch->id))->toHaveCount(1)
        ->and($service->snapshotsForDateAcrossBranches('operational_kpi_pack', '2026-03-27'))->toHaveCount(2)
        ->and($service->latestSuccessfulSnapshotForDate('operational_kpi_pack', '2026-03-27', $branch->id)?->is($expected))->toBeTrue();
});

it('finds the previous successful snapshot for compare commands', function (): void {
    $branch = Branch::factory()->create();

    $previous = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-26',
        'branch_id' => $branch->id,
        'branch_scope_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-27 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-27 06:00:00'),
        'payload' => ['booking_count' => 4],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-27 00:15:00')->toIso8601String()],
    ]);

    $current = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => '2026-03-27',
        'branch_id' => $branch->id,
        'branch_scope_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => Carbon::parse('2026-03-28 00:15:00'),
        'sla_due_at' => Carbon::parse('2026-03-28 06:00:00'),
        'payload' => ['booking_count' => 5],
        'lineage' => ['generated_at' => Carbon::parse('2026-03-28 00:15:00')->toIso8601String()],
    ]);

    expect(app(ReportSnapshotReadModelService::class)->previousSuccessfulSnapshot($current)?->is($previous))->toBeTrue();
});
