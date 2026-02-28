<?php

use App\Filament\Resources\PlanItems\PlanItemResource;
use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Policies\PlanItemPolicy;
use App\Policies\TreatmentSessionPolicy;

it('enforces branch isolation for plan item and treatment session policies', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $managerB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $managerB->assignRole('Manager');

    $patientA = createPatientForBranch($branchA);
    $planA = TreatmentPlan::factory()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
    ]);

    $planItemA = PlanItem::query()->create([
        'treatment_plan_id' => $planA->id,
        'name' => 'Dieu tri nha khoa tong quat',
        'quantity' => 1,
        'price' => 1200000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    $sessionA = TreatmentSession::query()->create([
        'treatment_plan_id' => $planA->id,
        'plan_item_id' => $planItemA->id,
        'status' => 'scheduled',
    ]);

    $planItemPolicy = app(PlanItemPolicy::class);
    $sessionPolicy = app(TreatmentSessionPolicy::class);

    expect($planItemA->resolveBranchId())->toBe($branchA->id)
        ->and($sessionA->resolveBranchId())->toBe($branchA->id)
        ->and($planItemPolicy->view($managerA, $planItemA))->toBeTrue()
        ->and($planItemPolicy->view($managerB, $planItemA))->toBeFalse()
        ->and($sessionPolicy->view($managerA, $sessionA))->toBeTrue()
        ->and($sessionPolicy->view($managerB, $sessionA))->toBeFalse();
});

it('filters plan item and treatment session resources by accessible branches', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $planA = TreatmentPlan::factory()->create([
        'patient_id' => createPatientForBranch($branchA)->id,
        'branch_id' => $branchA->id,
    ]);
    $planB = TreatmentPlan::factory()->create([
        'patient_id' => createPatientForBranch($branchB)->id,
        'branch_id' => $branchB->id,
    ]);

    $itemA = PlanItem::query()->create([
        'treatment_plan_id' => $planA->id,
        'name' => 'Dieu tri A',
        'quantity' => 1,
        'price' => 500000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);
    $itemB = PlanItem::query()->create([
        'treatment_plan_id' => $planB->id,
        'name' => 'Dieu tri B',
        'quantity' => 1,
        'price' => 700000,
        'status' => PlanItem::STATUS_PENDING,
        'approval_status' => PlanItem::APPROVAL_PROPOSED,
    ]);

    $sessionA = TreatmentSession::query()->create([
        'treatment_plan_id' => $planA->id,
        'plan_item_id' => $itemA->id,
        'status' => 'scheduled',
    ]);
    $sessionB = TreatmentSession::query()->create([
        'treatment_plan_id' => $planB->id,
        'plan_item_id' => $itemB->id,
        'status' => 'scheduled',
    ]);

    $this->actingAs($managerA);

    $planItemIds = PlanItemResource::getEloquentQuery()
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    $sessionIds = TreatmentSessionResource::getEloquentQuery()
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    expect($planItemIds)->toContain($itemA->id)
        ->and($planItemIds)->not->toContain($itemB->id)
        ->and($sessionIds)->toContain($sessionA->id)
        ->and($sessionIds)->not->toContain($sessionB->id);
});

function createPatientForBranch(Branch $branch): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
}
