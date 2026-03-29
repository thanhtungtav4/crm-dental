<?php

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

it('summarizes live revenue data for selected branches and date range', function (): void {
    $inRangeDate = '2026-03-29';
    $outOfRangeDate = '2026-03-24';

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctorA = User::factory()->create(['branch_id' => $branchA->id]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create(['branch_id' => $branchB->id]);
    $doctorB->assignRole('Doctor');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::query()->create([
        'customer_id' => $customerA->id,
        'full_name' => 'Patient branch A',
        'phone' => '0900000001',
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::query()->create([
        'customer_id' => $customerB->id,
        'full_name' => 'Patient branch B',
        'phone' => '0900000002',
        'first_branch_id' => $branchB->id,
    ]);

    $category = ServiceCategory::query()->create([
        'name' => 'Live revenue',
        'code' => 'live-revenue',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Revenue procedure',
        'code' => 'revenue-procedure',
        'default_price' => 1_000_000,
        'active' => true,
    ]);

    $planA = TreatmentPlan::query()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'title' => 'Branch A plan',
        'status' => TreatmentPlan::STATUS_APPROVED,
        'total_cost' => 0,
    ]);

    $planB = TreatmentPlan::query()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'title' => 'Branch B plan',
        'status' => TreatmentPlan::STATUS_APPROVED,
        'total_cost' => 0,
    ]);

    $currentPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $planA->id,
        'service_id' => $service->id,
        'name' => 'Branch A current',
        'quantity' => 1,
        'price' => 1_000_000,
        'final_amount' => 950_000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    PlanItem::query()
        ->whereKey($currentPlanItem->id)
        ->update([
            'created_at' => $inRangeDate.' 10:00:00',
            'updated_at' => $inRangeDate.' 10:00:00',
        ]);

    $oldPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $planA->id,
        'service_id' => $service->id,
        'name' => 'Branch A old',
        'quantity' => 1,
        'price' => 1_000_000,
        'final_amount' => 900_000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    PlanItem::query()
        ->whereKey($oldPlanItem->id)
        ->update([
            'created_at' => $outOfRangeDate.' 10:00:00',
            'updated_at' => $outOfRangeDate.' 10:00:00',
        ]);

    $otherBranchPlanItem = PlanItem::query()->create([
        'treatment_plan_id' => $planB->id,
        'service_id' => $service->id,
        'name' => 'Branch B current',
        'quantity' => 1,
        'price' => 1_000_000,
        'final_amount' => 1_200_000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
    ]);

    PlanItem::query()
        ->whereKey($otherBranchPlanItem->id)
        ->update([
            'created_at' => $inRangeDate.' 11:00:00',
            'updated_at' => $inRangeDate.' 11:00:00',
        ]);

    $summary = app(HotReportAggregateReadModelService::class)->liveRevenueSummary(
        [$branchA->id],
        '2026-03-28',
        '2026-03-29',
    );

    expect($summary)->toBe([
        'total_procedures' => 1,
        'total_revenue' => 950_000.0,
    ]);
});

it('summarizes live care data using direct and legacy patient branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $staffA = User::factory()->create(['branch_id' => $branchA->id]);
    $staffA->assignRole('CSKH');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::query()->create([
        'customer_id' => $customerA->id,
        'full_name' => 'Care patient A',
        'phone' => '0900000011',
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::query()->create([
        'customer_id' => $customerB->id,
        'full_name' => 'Care patient B',
        'phone' => '0900000012',
        'first_branch_id' => $branchB->id,
    ]);

    Note::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'user_id' => $staffA->id,
        'type' => 'care',
        'content' => 'Direct branch scope',
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_DONE,
        'care_at' => now(),
    ]);

    Note::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => null,
        'user_id' => $staffA->id,
        'type' => 'care',
        'content' => 'Legacy patient scope',
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_at' => now(),
    ]);

    Note::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'user_id' => $staffA->id,
        'type' => 'care',
        'content' => 'Other branch',
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_DONE,
        'care_at' => now(),
    ]);

    $summary = app(HotReportAggregateReadModelService::class)->liveCareSummary(
        [$branchA->id],
        now()->subDay()->toDateString(),
        now()->toDateString(),
    );

    expect($summary)->toBe([
        'total' => 2,
        'completed' => 1,
        'planned' => 1,
    ]);
});
