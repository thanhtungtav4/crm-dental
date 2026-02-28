<?php

use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Patient;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Policies\TreatmentMaterialPolicy;
use Illuminate\Validation\ValidationException;

it('creates plan → session → materials and updates stock & logs', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => null,
        'status' => 'in_progress',
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $user->id,
        'status' => 'done',
    ]);

    $material = Material::factory()->create([
        'stock_qty' => 100,
    ]);

    $usage = TreatmentMaterial::create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'quantity' => 5,
        'cost' => 0,
        'used_by' => $user->id,
    ]);

    expect($material->fresh()->stock_qty)->toBe(95)
        ->and(InventoryTransaction::where('material_id', $material->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'out')
            ->exists())->toBeTrue();

    $usage->delete();

    expect($material->fresh()->stock_qty)->toBe(100)
        ->and(InventoryTransaction::where('material_id', $material->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'adjust')
            ->exists())->toBeTrue();
});

it('rejects invalid material usage quantity values', function () {
    $user = User::factory()->create();
    $patient = Patient::factory()->create();
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $user->id,
    ]);
    $material = Material::factory()->create([
        'stock_qty' => 3,
    ]);

    expect(fn () => TreatmentMaterial::query()->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'quantity' => 0,
        'used_by' => $user->id,
    ]))->toThrow(ValidationException::class, 'Số lượng vật tư phải lớn hơn 0.');

    expect(fn () => TreatmentMaterial::query()->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'quantity' => 4,
        'used_by' => $user->id,
    ]))->toThrow(ValidationException::class, 'Số lượng sử dụng vượt quá tồn kho hiện tại.');
});

it('enforces treatment material access by branch in policy and resource query', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create(['branch_id' => $branchA->id]);
    $managerA->assignRole('Manager');

    $managerB = User::factory()->create(['branch_id' => $branchB->id]);
    $managerB->assignRole('Manager');

    $patientA = Patient::factory()->create(['first_branch_id' => $branchA->id]);
    $patientB = Patient::factory()->create(['first_branch_id' => $branchB->id]);

    $planA = TreatmentPlan::factory()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $managerA->id,
        'branch_id' => $branchA->id,
        'status' => 'in_progress',
    ]);

    $planB = TreatmentPlan::factory()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $managerB->id,
        'branch_id' => $branchB->id,
        'status' => 'in_progress',
    ]);

    $sessionA = TreatmentSession::factory()->create(['treatment_plan_id' => $planA->id]);
    $sessionB = TreatmentSession::factory()->create(['treatment_plan_id' => $planB->id]);

    $materialA = Material::factory()->create(['branch_id' => $branchA->id, 'stock_qty' => 100]);
    $materialB = Material::factory()->create(['branch_id' => $branchB->id, 'stock_qty' => 100]);

    $usageA = TreatmentMaterial::query()->create([
        'treatment_session_id' => $sessionA->id,
        'material_id' => $materialA->id,
        'quantity' => 1,
        'used_by' => $managerA->id,
    ]);

    $usageB = TreatmentMaterial::query()->create([
        'treatment_session_id' => $sessionB->id,
        'material_id' => $materialB->id,
        'quantity' => 1,
        'used_by' => $managerB->id,
    ]);

    $policy = app(TreatmentMaterialPolicy::class);

    expect($policy->view($managerA, $usageA))->toBeTrue()
        ->and($policy->view($managerA, $usageB))->toBeFalse();

    $this->actingAs($managerA);

    expect(TreatmentMaterialResource::getEloquentQuery()->pluck('id')->all())
        ->toEqualCanonicalizing([$usageA->id])
        ->not->toContain($usageB->id);
});
