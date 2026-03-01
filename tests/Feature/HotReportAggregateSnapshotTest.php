<?php

use App\Models\Branch;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TreatmentPlan;
use App\Models\User;

it('snapshots hot report aggregates for revenue and care queues', function (): void {
    $branch = Branch::factory()->create();
    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
    ]);
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $category = ServiceCategory::query()->create([
        'name' => 'Implant',
        'code' => 'implant',
        'active' => true,
    ]);

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Cắm implant',
        'code' => 'implant-single',
        'default_price' => 2_000_000,
        'active' => true,
    ]);

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'title' => 'Kế hoạch test aggregate',
        'status' => TreatmentPlan::STATUS_APPROVED,
        'total_cost' => 0,
    ]);

    PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'service_id' => $service->id,
        'name' => 'Implant trụ 16',
        'quantity' => 1,
        'price' => 2_000_000,
        'final_amount' => 1_800_000,
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
        'content' => 'Nhắc tái khám',
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_at' => now(),
    ]);

    $this->artisan('reports:snapshot-hot-aggregates', [
        '--date' => now()->toDateString(),
        '--branch_id' => $branch->id,
    ])
        ->expectsOutputToContain('HOT_REPORT_AGGREGATE_REVENUE_ROWS')
        ->expectsOutputToContain('HOT_REPORT_AGGREGATE_CARE_ROWS')
        ->assertSuccessful();

    $revenue = ReportRevenueDailyAggregate::query()
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_scope_id', $branch->id)
        ->where('service_id', $service->id)
        ->first();

    expect($revenue)->not->toBeNull()
        ->and((int) $revenue?->total_count)->toBe(1)
        ->and((float) $revenue?->total_revenue)->toEqualWithDelta(1_800_000, 0.01)
        ->and((string) $revenue?->service_name)->toBe('Cắm implant');

    $care = ReportCareQueueDailyAggregate::query()
        ->whereDate('snapshot_date', now()->toDateString())
        ->where('branch_scope_id', $branch->id)
        ->where('care_type', 'recall_recare')
        ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))
        ->first();

    expect($care)->not->toBeNull()
        ->and((int) $care?->total_count)->toBe(1);
});
