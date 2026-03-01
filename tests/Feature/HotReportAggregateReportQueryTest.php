<?php

use App\Filament\Pages\Reports\CustomsCareStatistical;
use App\Filament\Pages\Reports\RevenueStatistical;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\Service;
use App\Models\ServiceCategory;

it('switches revenue and care reports to aggregate tables when pre-aggregated data exists', function (): void {
    $category = ServiceCategory::query()->create([
        'name' => 'Phục hình',
        'code' => 'prosthodontics',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Bọc sứ',
        'code' => 'crown',
        'default_price' => 1_500_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 2,
        'total_revenue' => 3_000_000,
        'generated_at' => now(),
    ]);

    ReportCareQueueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'care_type' => 'recall_recare',
        'care_type_label' => 'Recall',
        'care_status' => 'not_started',
        'care_status_label' => 'Chưa bắt đầu',
        'total_count' => 4,
        'generated_at' => now(),
    ]);

    $revenuePage = app(RevenueStatistical::class);
    $carePage = app(CustomsCareStatistical::class);

    $revenueQueryMethod = new \ReflectionMethod($revenuePage, 'getTableQuery');
    $revenueQueryMethod->setAccessible(true);
    $revenueQuery = $revenueQueryMethod->invoke($revenuePage);

    $careQueryMethod = new \ReflectionMethod($carePage, 'getTableQuery');
    $careQueryMethod->setAccessible(true);
    $careQuery = $careQueryMethod->invoke($carePage);

    expect($revenueQuery->toSql())
        ->toContain('report_revenue_daily_aggregates')
        ->and($careQuery->toSql())->toContain('report_care_queue_daily_aggregates');
});
