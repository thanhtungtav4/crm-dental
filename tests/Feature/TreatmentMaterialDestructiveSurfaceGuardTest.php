<?php

use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Policies\TreatmentMaterialPolicy;
use App\Services\TreatmentMaterialUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('removes destructive treatment material surfaces from the ui', function (): void {
    $table = File::get(app_path('Filament/Resources/TreatmentMaterials/Tables/TreatmentMaterialsTable.php'));

    expect($table)
        ->not->toContain("Action::make('delete_usage')")
        ->not->toContain('TreatmentMaterialUsageService::class')
        ->not->toContain('Hoan tac ghi nhan');
});

it('denies delete restore and force delete for treatment materials via policy', function (): void {
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
        'stock_qty' => 8,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TMAT-GUARD-001',
        'expiry_date' => now()->addMonths(4)->toDateString(),
        'quantity' => 8,
        'purchase_price' => 15_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'used_by' => $manager->id,
    ]);

    $policy = app(TreatmentMaterialPolicy::class);

    expect($policy->delete($manager, $usage))->toBeFalse()
        ->and($policy->restore($manager, $usage))->toBeFalse()
        ->and($policy->forceDelete($manager, $usage))->toBeFalse()
        ->and($policy->restoreAny($manager))->toBeFalse()
        ->and($policy->forceDeleteAny($manager))->toBeFalse();
});

it('blocks direct treatment material delete attempts at model layer', function (): void {
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
        'stock_qty' => 8,
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TMAT-GUARD-002',
        'expiry_date' => now()->addMonths(4)->toDateString(),
        'quantity' => 8,
        'purchase_price' => 15_000,
        'received_date' => today()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'used_by' => $manager->id,
    ]);

    expect(fn () => $usage->delete())
        ->toThrow(ValidationException::class, 'TreatmentMaterialUsageService');
});
