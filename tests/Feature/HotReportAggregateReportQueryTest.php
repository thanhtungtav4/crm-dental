<?php

use App\Filament\Pages\Reports\CustomsCareStatistical;
use App\Filament\Pages\Reports\RevenueStatistical;
use App\Filament\Pages\Reports\TrickGroupStatistical;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TreatmentPlan;
use App\Models\User;
use Livewire\Livewire;

it('switches revenue and care reports to aggregate tables when bounded pre-aggregated data exists', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

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

    $this->actingAs($admin);

    $revenuePage = Livewire::test(RevenueStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();
    $carePage = Livewire::test(CustomsCareStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();
    $trickGroupPage = Livewire::test(TrickGroupStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();

    $revenueQueryMethod = new \ReflectionMethod($revenuePage, 'getTableQuery');
    $revenueQueryMethod->setAccessible(true);
    $revenueQuery = $revenueQueryMethod->invoke($revenuePage);

    $careQueryMethod = new \ReflectionMethod($carePage, 'getTableQuery');
    $careQueryMethod->setAccessible(true);
    $careQuery = $careQueryMethod->invoke($carePage);
    $trickGroupQueryMethod = new \ReflectionMethod($trickGroupPage, 'getTableQuery');
    $trickGroupQueryMethod->setAccessible(true);
    $trickGroupQuery = $trickGroupQueryMethod->invoke($trickGroupPage);

    expect($revenueQuery->toSql())
        ->toContain('report_revenue_daily_aggregates')
        ->and($careQuery->toSql())->toContain('report_care_queue_daily_aggregates')
        ->and($trickGroupQuery->toSql())->toContain('report_revenue_daily_aggregates');
});

it('falls back to raw report queries when aggregate range is unbounded', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $category = ServiceCategory::query()->create([
        'name' => 'Tong hop',
        'code' => 'tong-hop-raw',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Thu thuat raw',
        'code' => 'thu-thuat-raw',
        'default_price' => 1_200_000,
        'active' => true,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'title' => 'Raw fallback',
        'status' => TreatmentPlan::STATUS_APPROVED,
        'total_cost' => 0,
    ]);

    PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $service->id,
        'name' => 'Raw item',
        'quantity' => 1,
        'price' => 1_200_000,
        'final_amount' => 1_100_000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Note::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'user_id' => $doctor->id,
        'type' => 'care',
        'content' => 'Raw care',
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_at' => now(),
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 99,
        'total_revenue' => 99_000_000,
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
        'total_count' => 99,
        'generated_at' => now(),
    ]);

    $this->actingAs($admin);

    $revenuePage = Livewire::test(RevenueStatistical::class)->instance();
    $carePage = Livewire::test(CustomsCareStatistical::class)->instance();
    $trickGroupPage = Livewire::test(TrickGroupStatistical::class)->instance();

    $revenueQueryMethod = new \ReflectionMethod($revenuePage, 'getTableQuery');
    $revenueQueryMethod->setAccessible(true);
    $careQueryMethod = new \ReflectionMethod($carePage, 'getTableQuery');
    $careQueryMethod->setAccessible(true);
    $trickGroupQueryMethod = new \ReflectionMethod($trickGroupPage, 'getTableQuery');
    $trickGroupQueryMethod->setAccessible(true);

    expect($revenueQueryMethod->invoke($revenuePage)->toSql())
        ->toContain('plan_items')
        ->and($careQueryMethod->invoke($carePage)->toSql())->toContain('select care_type, care_status')
        ->and($trickGroupQueryMethod->invoke($trickGroupPage)->toSql())->toContain('plan_items');
});

it('falls back to raw report queries when today aggregate is stale', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $category = ServiceCategory::query()->create([
        'name' => 'Stale aggregate',
        'code' => 'stale-aggregate',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Revenue stale',
        'code' => 'revenue-stale',
        'default_price' => 100_000,
        'active' => true,
    ]);

    ReportRevenueDailyAggregate::query()->create([
        'snapshot_date' => now()->toDateString(),
        'branch_id' => null,
        'branch_scope_id' => 0,
        'service_id' => $service->id,
        'service_name' => $service->name,
        'category_name' => $category->name,
        'total_count' => 1,
        'total_revenue' => 100_000,
        'generated_at' => now()->subDays(2),
    ]);

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

    $this->actingAs($admin);

    $revenuePage = Livewire::test(RevenueStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();
    $carePage = Livewire::test(CustomsCareStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();
    $trickGroupPage = Livewire::test(TrickGroupStatistical::class)
        ->set('tableFilters.date_range.from', now()->toDateString())
        ->set('tableFilters.date_range.until', now()->toDateString())
        ->instance();

    $revenueQueryMethod = new \ReflectionMethod($revenuePage, 'getTableQuery');
    $revenueQueryMethod->setAccessible(true);
    $careQueryMethod = new \ReflectionMethod($carePage, 'getTableQuery');
    $careQueryMethod->setAccessible(true);
    $trickGroupQueryMethod = new \ReflectionMethod($trickGroupPage, 'getTableQuery');
    $trickGroupQueryMethod->setAccessible(true);

    expect($revenueQueryMethod->invoke($revenuePage)->toSql())
        ->toContain('plan_items')
        ->and($careQueryMethod->invoke($carePage)->toSql())->toContain('select care_type, care_status')
        ->and($trickGroupQueryMethod->invoke($trickGroupPage)->toSql())->toContain('plan_items');
});
