<?php

use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns models from treatment progress update boundaries', function (): void {
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $plan = TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $patient->first_branch_id,
        'title' => 'Treatment progress return contract',
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::query()->create([
        'treatment_plan_id' => $plan->id,
        'name' => 'Implant 26',
        'quantity' => 1,
        'price' => 12000000,
        'estimated_cost' => 12000000,
        'actual_cost' => 9000000,
        'required_visits' => 2,
        'completed_visits' => 1,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'patient_approved' => true,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    $updatedPlanItem = $planItem->updateProgress();

    expect($updatedPlanItem)->toBeInstanceOf(PlanItem::class)
        ->and($updatedPlanItem->is($planItem))->toBeTrue()
        ->and($updatedPlanItem->progress_percentage)->toBe(50)
        ->and($updatedPlanItem->status)->toBe(PlanItem::STATUS_IN_PROGRESS);

    $updatedPlan = $plan->fresh()->updateProgress();

    expect($updatedPlan)->toBeInstanceOf(TreatmentPlan::class)
        ->and($updatedPlan->is($plan))->toBeTrue()
        ->and($updatedPlan->progress_percentage)->toBe(50)
        ->and($updatedPlan->completed_visits)->toBe(1)
        ->and($updatedPlan->total_visits)->toBe(2);
});
