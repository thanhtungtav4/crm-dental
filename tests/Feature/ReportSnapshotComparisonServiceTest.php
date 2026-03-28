<?php

use App\Models\ReportSnapshot;
use App\Services\ReportSnapshotComparisonService;

it('builds drift-aware snapshot comparison with numeric and scalar diffs', function (): void {
    $baseline = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'schema_version' => 'operational_kpi_pack.v1',
        'snapshot_date' => '2026-03-27',
        'branch_id' => null,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now()->subDay(),
        'sla_due_at' => now()->subDay()->addHour(),
        'payload' => [
            'booking_count' => 10,
            'booking_to_visit_rate' => 50,
            'top_branch' => 'Q1',
        ],
        'payload_checksum' => hash('sha256', 'baseline'),
        'lineage' => ['formula_signature' => hash('sha256', 'formula'), 'source_signature' => hash('sha256', 'source')],
        'lineage_checksum' => hash('sha256', 'baseline_lineage'),
        'drift_status' => ReportSnapshot::DRIFT_NONE,
    ]);

    $current = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'schema_version' => 'operational_kpi_pack.v1',
        'snapshot_date' => '2026-03-28',
        'branch_id' => null,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => [
            'booking_count' => 12,
            'booking_to_visit_rate' => 60,
            'top_branch' => 'HN-CG',
        ],
        'payload_checksum' => hash('sha256', 'current'),
        'lineage' => ['formula_signature' => hash('sha256', 'formula'), 'source_signature' => hash('sha256', 'source')],
        'lineage_checksum' => hash('sha256', 'current_lineage'),
        'drift_status' => ReportSnapshot::DRIFT_FORMULA_CHANGED,
        'drift_details' => ['formula_changed' => true],
        'compared_snapshot_id' => $baseline->id,
    ]);

    $comparison = app(ReportSnapshotComparisonService::class)->compare($baseline, $current);

    expect($comparison['baseline']['id'])->toBe($baseline->id)
        ->and($comparison['current']['id'])->toBe($current->id)
        ->and($comparison['drift'])->toBe([
            'status' => ReportSnapshot::DRIFT_FORMULA_CHANGED,
            'details' => ['formula_changed' => true],
        ])
        ->and($comparison['metrics'])->toBe([
            [
                'metric' => 'booking_count',
                'baseline' => '10.00',
                'current' => '12.00',
                'delta' => '2.00',
                'delta_percent' => '20.00%',
            ],
            [
                'metric' => 'booking_to_visit_rate',
                'baseline' => '50.00',
                'current' => '60.00',
                'delta' => '10.00',
                'delta_percent' => '20.00%',
            ],
            [
                'metric' => 'top_branch',
                'baseline' => 'Q1',
                'current' => 'HN-CG',
                'delta' => 'changed',
                'delta_percent' => '-',
            ],
        ]);
});

it('renders unchanged non-numeric payload values without fake percentage math', function (): void {
    $comparison = app(ReportSnapshotComparisonService::class)->buildMetricDiff(
        ['meta' => ['owner' => 'manager@demo.ident.test']],
        ['meta' => ['owner' => 'manager@demo.ident.test']],
    );

    expect($comparison)->toBe([
        [
            'metric' => 'meta',
            'baseline' => '{"owner":"manager@demo.ident.test"}',
            'current' => '{"owner":"manager@demo.ident.test"}',
            'delta' => 'unchanged',
            'delta_percent' => '-',
        ],
    ]);
});
