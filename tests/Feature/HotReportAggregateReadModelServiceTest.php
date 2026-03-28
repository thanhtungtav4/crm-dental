<?php

use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\HotReportAggregateReadModelService;

it('summarizes revenue aggregates for the selected scope and date range', function (): void {
    $category = ServiceCategory::query()->create([
        'name' => 'Revenue aggregate',
        'code' => 'revenue-aggregate',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Aggregate service',
        'code' => 'aggregate-service',
        'default_price' => 1_000_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->subDay()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => 'Scope A day 1',
        'category_name' => 'Revenue',
        'total_count' => 2,
        'total_revenue' => 3_000_000,
        'generated_at' => now()->subHour(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => 'Scope A day 2',
        'category_name' => 'Revenue',
        'total_count' => 3,
        'total_revenue' => 4_500_000,
        'generated_at' => now(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 99,
        'service_id' => $service->id,
        'service_name' => 'Scope B day 2',
        'category_name' => 'Revenue',
        'total_count' => 9,
        'total_revenue' => 9_000_000,
        'generated_at' => now(),
    ]);

    $summary = app(HotReportAggregateReadModelService::class)->revenueSummary(
        [0],
        now()->subDay()->toDateString(),
        now()->toDateString(),
    );

    expect($summary)->toBe([
        'total_procedures' => 5,
        'total_revenue' => 7_500_000.0,
    ]);
});

it('summarizes care aggregates for selected scope and counts completed versus planned', function (): void {
    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'care_type' => 'recall_recare',
        'care_type_label' => 'Recall',
        'care_status' => 'done',
        'care_status_label' => 'Hoàn thành',
        'total_count' => 4,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'care_type' => 'no_show_recovery',
        'care_type_label' => 'No-show',
        'care_status' => 'not_started',
        'care_status_label' => 'Chưa bắt đầu',
        'total_count' => 3,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 99,
        'care_type' => 'recall_recare',
        'care_type_label' => 'Recall',
        'care_status' => 'done',
        'care_status_label' => 'Hoàn thành',
        'total_count' => 8,
        'latest_care_at' => now(),
        'generated_at' => now(),
    ]);

    $summary = app(HotReportAggregateReadModelService::class)->careSummary(
        [0],
        now()->toDateString(),
        now()->toDateString(),
    );

    expect($summary)->toBe([
        'total' => 7,
        'completed' => 4,
        'planned' => 3,
    ]);
});

it('builds aggregate breakdown queries against the shared hot-report tables', function (): void {
    $service = app(HotReportAggregateReadModelService::class);

    expect($service->revenueBreakdownQuery([0])->toSql())
        ->toContain('report_revenue_daily_aggregates')
        ->and($service->careBreakdownQuery([0])->toSql())
        ->toContain('report_care_queue_daily_aggregates');
});
