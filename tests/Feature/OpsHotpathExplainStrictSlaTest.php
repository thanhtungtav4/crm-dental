<?php

use App\Models\Branch;
use App\Models\User;

it('fails in strict mode when query p95 exceeds configured sla threshold', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $this->artisan('reports:explain-ops-hotpaths', [
        '--branch_id' => $branch->id,
        '--benchmark-runs' => 3,
        '--sla-p95-ms' => 0.001,
        '--strict' => true,
    ])
        ->expectsOutputToContain('EXPLAIN_SLA_VIOLATION_COUNT')
        ->expectsOutputToContain('Strict mode: phát hiện hot-path query vượt ngưỡng SLA p95.')
        ->assertFailed();
});
