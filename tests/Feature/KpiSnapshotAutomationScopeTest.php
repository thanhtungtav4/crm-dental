<?php

use App\Models\Branch;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

it('keeps system snapshot automation on global scope when no actor is authenticated', function (): void {
    Branch::factory()->count(2)->create();

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('success=3')
        ->assertSuccessful();
});

it('scopes manager snapshot automation to accessible branches when no branch is selected', function (): void {
    $branchA = Branch::factory()->create();
    Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $this->artisan('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('success=1')
        ->assertSuccessful();
});

it('rejects manager snapshot automation for an inaccessible branch', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    expect(fn () => Artisan::call('reports:snapshot-operational-kpis', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branchB->id,
        '--dry-run' => true,
    ]))->toThrow(
        ValidationException::class,
        'Ban khong co quyen chay report automation cho chi nhanh nay.',
    );
});

it('scopes snapshot SLA checks to accessible branches for managers', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchA->id,
        'branch_scope_id' => $branchA->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now(),
        'sla_due_at' => now()->addHour(),
        'payload' => ['booking_count' => 1],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    ReportSnapshot::query()->create([
        'snapshot_key' => 'operational_kpi_pack',
        'snapshot_date' => now()->toDateString(),
        'branch_id' => $branchB->id,
        'branch_scope_id' => $branchB->id,
        'status' => ReportSnapshot::STATUS_SUCCESS,
        'sla_status' => ReportSnapshot::SLA_ON_TIME,
        'generated_at' => now()->addHours(2),
        'sla_due_at' => now()->subHour(),
        'payload' => ['booking_count' => 1],
        'lineage' => ['generated_at' => now()->toIso8601String()],
    ]);

    $this->actingAs($manager);

    $this->artisan('reports:check-snapshot-sla', [
        '--date' => now()->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('on_time=1, late=0, stale=0, missing=0')
        ->assertSuccessful();
});

it('scopes hot aggregate snapshot automation to accessible branches for managers', function (): void {
    $branchA = Branch::factory()->create();
    Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $this->artisan('reports:snapshot-hot-aggregates', [
        '--date' => now()->toDateString(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('HOT_REPORT_AGGREGATE_BRANCH_COUNT: 1')
        ->assertSuccessful();
});

it('rejects hot aggregate snapshot automation for an inaccessible branch', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    expect(fn () => Artisan::call('reports:snapshot-hot-aggregates', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branchB->id,
        '--dry-run' => true,
    ]))->toThrow(
        ValidationException::class,
        'Ban khong co quyen chay report automation cho chi nhanh nay.',
    );
});
