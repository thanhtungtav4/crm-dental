<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;
use Illuminate\Database\QueryException;

it('enforces unique snapshot key per date and branch scope', function () {
    $branch = Branch::factory()->create();

    ReportSnapshot::query()->create([
        'snapshot_key' => 'ops_unique_case',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'payload' => ['kpi' => 1],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    expect(fn () => ReportSnapshot::query()->create([
        'snapshot_key' => 'ops_unique_case',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'payload' => ['kpi' => 2],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]))->toThrow(QueryException::class);
});

it('allows same snapshot key/date for different branches', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    ReportSnapshot::query()->create([
        'snapshot_key' => 'ops_branch_scope_case',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'payload' => ['value' => 1],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'ops_branch_scope_case',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'payload' => ['value' => 2],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    expect(ReportSnapshot::query()
        ->where('snapshot_key', 'ops_branch_scope_case')
        ->whereDate('snapshot_date', now()->toDateString())
        ->count())->toBe(2);
});
