<?php

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('denies delete restore and force delete for treatment plans via policy', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $manager->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    expect($manager->can('delete', $plan))->toBeFalse()
        ->and($manager->can('deleteAny', TreatmentPlan::class))->toBeFalse()
        ->and($manager->can('restore', $plan))->toBeFalse()
        ->and($manager->can('forceDelete', $plan))->toBeFalse()
        ->and($manager->can('restoreAny', TreatmentPlan::class))->toBeFalse()
        ->and($manager->can('forceDeleteAny', TreatmentPlan::class))->toBeFalse();
});

it('blocks direct treatment plan delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
    ]);

    expect(fn () => $plan->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});

it('denies delete restore and force delete for plan items via policy', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $manager->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);
    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    expect($manager->can('delete', $planItem))->toBeFalse()
        ->and($manager->can('deleteAny', PlanItem::class))->toBeFalse()
        ->and($manager->can('restore', $planItem))->toBeFalse()
        ->and($manager->can('forceDelete', $planItem))->toBeFalse()
        ->and($manager->can('restoreAny', PlanItem::class))->toBeFalse()
        ->and($manager->can('forceDeleteAny', PlanItem::class))->toBeFalse();
});

it('blocks direct plan item delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create();
    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);
    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
        'approval_status' => PlanItem::APPROVAL_APPROVED,
        'status' => PlanItem::STATUS_PENDING,
    ]);

    expect(fn () => $planItem->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
