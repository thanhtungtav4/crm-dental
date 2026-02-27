<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;

it('persists snapshot schema version and checksums with baseline comparison', function () {
    $branch = Branch::factory()->create();

    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => $yesterday,
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $baseline = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', $yesterday)
        ->where('branch_id', $branch->id)
        ->first();

    expect($baseline)->not->toBeNull();

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => $today,
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $current = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', $today)
        ->where('branch_id', $branch->id)
        ->first();

    expect($current)->not->toBeNull()
        ->and((string) $current?->schema_version)->toBe('operational_kpi_pack.v1')
        ->and((string) $current?->payload_checksum)->toHaveLength(64)
        ->and((string) $current?->lineage_checksum)->toHaveLength(64)
        ->and((int) $current?->compared_snapshot_id)->toBe((int) $baseline?->id)
        ->and((string) $current?->drift_status)->toBe(ReportSnapshot::DRIFT_NONE)
        ->and(data_get($current?->lineage, 'formula_signature'))->not->toBeNull()
        ->and(data_get($current?->lineage, 'source_signature'))->not->toBeNull();
});

it('marks schema drift when baseline schema version changed', function () {
    $branch = Branch::factory()->create();

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'schema_version' => 'legacy.v0',
        'snapshot_date' => now()->subDay()->toDateString(),
        'branch_id' => $branch->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now()->subDay(),
        'sla_due_at' => now()->subDay()->addHour(),
        'payload' => ['booking_count' => 1],
        'payload_checksum' => hash('sha256', 'legacy_payload'),
        'lineage' => [
            'formula_signature' => hash('sha256', 'legacy_formula'),
            'source_signature' => hash('sha256', 'legacy_source'),
        ],
        'lineage_checksum' => hash('sha256', 'legacy_lineage'),
        'drift_status' => ReportSnapshot::DRIFT_UNKNOWN,
    ]);

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])->assertSuccessful();

    $current = ReportSnapshot::query()
        ->where('snapshot_key', 'operational_kpi_pack')
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_id', $branch->id)
        ->first();

    expect($current)->not->toBeNull()
        ->and((string) $current?->drift_status)->toBe(ReportSnapshot::DRIFT_SCHEMA_CHANGED)
        ->and((bool) data_get($current?->drift_details, 'schema_changed'))->toBeTrue()
        ->and((string) data_get($current?->drift_details, 'baseline_schema_version'))->toBe('legacy.v0');
});

it('compares report snapshots and prints metric diff', function () {
    $left = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'schema_version' => 'operational_kpi_pack.v1',
        'snapshot_date' => now()->subDay()->toDateString(),
        'branch_id' => null,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now()->subDay(),
        'sla_due_at' => now()->subDay()->addHour(),
        'payload' => [
            'booking_count' => 10,
            'booking_to_visit_rate' => 50,
        ],
        'payload_checksum' => hash('sha256', 'left'),
        'lineage' => ['formula_signature' => hash('sha256', 'formula'), 'source_signature' => hash('sha256', 'source')],
        'lineage_checksum' => hash('sha256', 'left_lineage'),
        'drift_status' => ReportSnapshot::DRIFT_NONE,
    ]);

    $right = ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'schema_version' => 'operational_kpi_pack.v1',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => [
            'booking_count' => 12,
            'booking_to_visit_rate' => 60,
        ],
        'payload_checksum' => hash('sha256', 'right'),
        'lineage' => ['formula_signature' => hash('sha256', 'formula'), 'source_signature' => hash('sha256', 'source')],
        'lineage_checksum' => hash('sha256', 'right_lineage'),
        'drift_status' => ReportSnapshot::DRIFT_NONE,
        'compared_snapshot_id' => $left->id,
    ]);

    $this->artisan('reports:compare-snapshots', [
        'left_snapshot_id' => $left->id,
        'right_snapshot_id' => $right->id,
    ])
        ->expectsOutputToContain('Drift status: none')
        ->expectsOutputToContain('booking_count')
        ->expectsOutputToContain('booking_to_visit_rate')
        ->assertSuccessful();
});
