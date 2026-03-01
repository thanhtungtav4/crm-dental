<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('generates explain baseline artifact for operational hotpaths', function () {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $outputPath = storage_path('app/testing/explain/ops-hotpaths-baseline.json');
    @unlink($outputPath);

    $this->artisan('reports:explain-ops-hotpaths', [
        '--branch_id' => $branch->id,
        '--write' => $outputPath,
        '--benchmark-runs' => 3,
        '--sla-p95-ms' => 200,
    ])
        ->expectsOutputToContain('EXPLAIN_BASELINE_WRITTEN')
        ->expectsOutputToContain('EXPLAIN_SLA_VIOLATION_COUNT')
        ->assertSuccessful();

    expect(File::exists($outputPath))->toBeTrue();

    $payload = json_decode((string) File::get($outputPath), true, flags: JSON_THROW_ON_ERROR);
    $queries = collect((array) data_get($payload, 'queries', []));

    expect($queries->count())->toBe(6)
        ->and($queries->pluck('key')->all())->toEqualCanonicalizing([
            'notes_care_queue',
            'care_queue_daily_aggregate',
            'appointments_capacity',
            'payments_branch_aging',
            'invoices_branch_status',
            'revenue_daily_aggregate',
        ])
        ->and($queries->every(fn (array $query): bool => array_key_exists('plan_rows', $query)))->toBeTrue()
        ->and($queries->every(fn (array $query): bool => array_key_exists('full_scan_detected', $query)))->toBeTrue()
        ->and($queries->every(fn (array $query): bool => array_key_exists('p95_ms', $query)))->toBeTrue()
        ->and($queries->every(fn (array $query): bool => array_key_exists('sla_violated', $query)))->toBeTrue();
});
