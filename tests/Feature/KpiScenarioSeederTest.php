<?php

use App\Models\Branch;
use App\Models\OperationalKpiAlert;
use App\Models\User;
use Database\Seeders\KpiScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;

use function Pest\Laravel\seed;

it('creates kpi snapshot scenarios with on-time, stale, and missing coverage for admin checks', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()->where('email', 'admin@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($admin);

    $this->artisan('reports:check-snapshot-sla', [
        '--date' => KpiScenarioSeeder::snapshotDate(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('on_time=1, late=0, stale=1, missing=0')
        ->assertSuccessful();

    $branchId = Branch::query()->where('code', 'DN-HC')->value('id');

    $this->artisan('reports:check-snapshot-sla', [
        '--date' => KpiScenarioSeeder::snapshotDate(),
        '--branch_id' => $branchId,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('on_time=0, late=0, stale=0, missing=1')
        ->assertSuccessful();

    expect(OperationalKpiAlert::query()->where('metric_key', 'no_show_rate')->where('status', OperationalKpiAlert::STATUS_NEW)->exists())->toBeTrue()
        ->and(OperationalKpiAlert::query()->where('metric_key', 'chair_utilization_rate')->where('status', OperationalKpiAlert::STATUS_ACK)->exists())->toBeTrue()
        ->and(OperationalKpiAlert::query()->where('metric_key', 'production_efficiency')->where('status', OperationalKpiAlert::STATUS_RESOLVED)->exists())->toBeTrue();
});

it('scopes seeded kpi smoke scenarios to the manager branch and keeps hot aggregate dry-run usable', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($manager);

    $this->artisan('reports:check-snapshot-sla', [
        '--date' => KpiScenarioSeeder::snapshotDate(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('on_time=1, late=0, stale=0, missing=0')
        ->assertSuccessful();

    $this->artisan('reports:snapshot-hot-aggregates', [
        '--date' => KpiScenarioSeeder::snapshotDate(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('HOT_REPORT_AGGREGATE_BRANCH_COUNT: 1')
        ->assertSuccessful();
});
