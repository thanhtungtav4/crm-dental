<?php

use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\HotReportAggregateReadinessService;

it('accepts fresh aggregate coverage for every day in the selected revenue range', function (): void {
    $category = ServiceCategory::query()->create([
        'name' => 'Freshness',
        'code' => 'freshness-range',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Range revenue',
        'code' => 'range-revenue',
        'default_price' => 100_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->subDay()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => 'Range day 1',
        'category_name' => 'Test',
        'total_count' => 1,
        'total_revenue' => 100_000,
        'generated_at' => now()->subHour(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => 'Range day 2',
        'category_name' => 'Test',
        'total_count' => 1,
        'total_revenue' => 200_000,
        'generated_at' => now(),
    ]);

    $service = app(HotReportAggregateReadinessService::class);

    expect($service->shouldUseRevenueAggregate(
        [0],
        now()->subDay()->toDateString(),
        now()->toDateString(),
    ))->toBeTrue();
});

it('rejects care aggregate coverage when today snapshot is stale', function (): void {
    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'care_type' => 'recall_recare',
        'care_type_label' => 'Recall',
        'care_status' => 'not_started',
        'care_status_label' => 'Chưa bắt đầu',
        'total_count' => 1,
        'generated_at' => now()->subDays(2),
    ]);

    $service = app(HotReportAggregateReadinessService::class);

    expect($service->shouldUseCareAggregate(
        [0],
        now()->toDateString(),
        now()->toDateString(),
    ))->toBeFalse();
});

it('rejects aggregate coverage when a selected day is missing', function (): void {
    $category = ServiceCategory::query()->create([
        'name' => 'Freshness missing',
        'code' => 'freshness-missing',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Missing revenue day',
        'code' => 'missing-revenue-day',
        'default_price' => 100_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => 'Only one day',
        'category_name' => 'Test',
        'total_count' => 1,
        'total_revenue' => 100_000,
        'generated_at' => now(),
    ]);

    $service = app(HotReportAggregateReadinessService::class);

    expect($service->shouldUseRevenueAggregate(
        [0],
        now()->subDay()->toDateString(),
        now()->toDateString(),
    ))->toBeFalse();
});
