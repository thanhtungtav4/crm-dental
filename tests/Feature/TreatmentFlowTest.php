<?php

use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Policies\TreatmentMaterialPolicy;
use App\Services\TreatmentMaterialUsageService;
use Illuminate\Validation\ValidationException;

it('creates plan -> session -> materials and updates stock and logs', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Manager');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $user->id,
        'status' => 'done',
    ]);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 100,
        'cost_price' => 125000,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TFLOW-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 100,
        'purchase_price' => 125000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 5,
        'cost' => 0,
        'used_by' => $user->id,
    ]);

    expect($material->fresh()->stock_qty)->toBe(95)
        ->and($batch->fresh()->quantity)->toBe(95)
        ->and(InventoryTransaction::query()->where('material_id', $material->id)
            ->where('material_batch_id', $batch->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'out')
            ->where('unit_cost', 125000)
            ->exists())->toBeTrue();

    app(TreatmentMaterialUsageService::class)->delete($usage);

    expect($material->fresh()->stock_qty)->toBe(100)
        ->and($batch->fresh()->quantity)->toBe(100)
        ->and(InventoryTransaction::query()->where('material_id', $material->id)
            ->where('material_batch_id', $batch->id)
            ->where('treatment_session_id', $session->id)
            ->where('type', 'adjust')
            ->exists())->toBeTrue();
});

it('rejects invalid material usage quantity values', function (): void {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Manager');
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $user->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $user->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 3,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TFLOW-002',
        'expiry_date' => now()->addMonths(4)->toDateString(),
        'quantity' => 3,
        'purchase_price' => 10000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($user);

    expect(fn () => app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 0,
        'used_by' => $user->id,
    ]))->toThrow(ValidationException::class, 'So luong vat tu phai lon hon 0.');

    expect(fn () => app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 4,
        'used_by' => $user->id,
    ]))->toThrow(ValidationException::class, 'So luong su dung vuot qua ton kho cua lo vat tu da chon.');
});

it('enforces treatment material access by branch in policy and resource query', function (): void {
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

    $batchA = MaterialBatch::query()->create([
        'material_id' => $materialA->id,
        'batch_number' => 'LOT-TFLOW-003',
        'expiry_date' => now()->addMonths(5)->toDateString(),
        'quantity' => 100,
        'purchase_price' => 10000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);
    $batchB = MaterialBatch::query()->create([
        'material_id' => $materialB->id,
        'batch_number' => 'LOT-TFLOW-004',
        'expiry_date' => now()->addMonths(5)->toDateString(),
        'quantity' => 100,
        'purchase_price' => 10000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($managerA);
    $usageA = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $sessionA->id,
        'material_id' => $materialA->id,
        'batch_id' => $batchA->id,
        'quantity' => 1,
        'used_by' => $managerA->id,
    ]);

    $this->actingAs($managerB);
    $usageB = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $sessionB->id,
        'material_id' => $materialB->id,
        'batch_id' => $batchB->id,
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

it('rejects material usage when session branch and material branch do not match', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $staff = User::factory()->create(['branch_id' => $branchA->id]);
    $staff->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branchA->id]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $staff->id,
        'branch_id' => $branchA->id,
        'status' => 'in_progress',
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    $materialOtherBranch = Material::factory()->create([
        'branch_id' => $branchB->id,
        'stock_qty' => 10,
    ]);
    $batchOtherBranch = MaterialBatch::query()->create([
        'material_id' => $materialOtherBranch->id,
        'batch_number' => 'LOT-TFLOW-005',
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 10000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs($staff);

    expect(fn () => app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $materialOtherBranch->id,
        'batch_id' => $batchOtherBranch->id,
        'quantity' => 1,
        'used_by' => $staff->id,
    ]))->toThrow(ValidationException::class, 'Vat tu khong cung chi nhanh voi phien dieu tri da chon.');
});

it('loads treatment material create page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.treatment-materials.create'))
        ->assertSuccessful()
        ->assertSee('Phiên điều trị')
        ->assertSee('Lô vật tư');
});
