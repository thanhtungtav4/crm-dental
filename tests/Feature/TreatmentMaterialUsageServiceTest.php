<?php

use App\Models\Branch;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\TreatmentMaterial;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\TreatmentMaterialUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('records treatment material usage against a specific batch and inventory ledger', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $manager->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
        'cost_price' => 15_000,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TM-001',
        'expiry_date' => now()->addMonths(5)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 15_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 3,
    ]);

    expect($usage->batch_id)->toBe($batch->id)
        ->and($material->fresh()->stock_qty)->toBe(7)
        ->and($batch->fresh()->quantity)->toBe(7)
        ->and(InventoryTransaction::query()
            ->where('treatment_session_id', $session->id)
            ->where('material_batch_id', $batch->id)
            ->where('type', 'out')
            ->exists())->toBeTrue();
});

it('blocks direct model create to force service boundary', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $manager->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TM-002',
        'expiry_date' => now()->addMonths(2)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 12_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect(fn () => TreatmentMaterial::query()->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'used_by' => $manager->id,
    ]))->toThrow(ValidationException::class, 'TreatmentMaterialUsageService');
});

it('records treatment material usage with the authenticated actor even if payload is forged', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $foreignStaff = User::factory()->create();
    $foreignStaff->assignRole('Manager');

    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $manager->id,
        'branch_id' => $branch->id,
        'status' => 'in_progress',
    ]);
    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'doctor_id' => $manager->id,
    ]);
    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 10,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TM-003',
        'expiry_date' => now()->addMonths(4)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 20_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
        'used_by' => $foreignStaff->id,
    ]);

    expect($usage->used_by)->toBe($manager->id)
        ->and(InventoryTransaction::query()
            ->where('treatment_session_id', $session->id)
            ->where('material_batch_id', $batch->id)
            ->value('created_by'))->toBe($manager->id);
});
