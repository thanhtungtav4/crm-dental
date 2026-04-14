<?php

use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Concurrency;
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
            ->exists())->toBeTrue()
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
            ->where('entity_id', $session->id)
            ->where('action', AuditLog::ACTION_CREATE)
            ->where('metadata->trigger', 'treatment_material_usage_recorded')
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

it('keeps inventory transactions immutable after material usage is posted', function (): void {
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
        'batch_number' => 'LOT-TM-004',
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 15_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
    ]);

    $transaction = InventoryTransaction::query()
        ->where('treatment_session_id', $session->id)
        ->where('material_batch_id', $batch->id)
        ->firstOrFail();

    expect(fn () => $transaction->update(['note' => 'Sửa ledger']))
        ->toThrow(ValidationException::class, 'immutable')
        ->and(fn () => $transaction->delete())
        ->toThrow(ValidationException::class, 'immutable');
});

it('records a reversal audit when treatment material usage is reverted', function (): void {
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
        'name' => 'Gạc nha khoa',
        'unit' => 'miếng',
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TM-005',
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'quantity' => 10,
        'purchase_price' => 15_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 2,
    ]);

    app(TreatmentMaterialUsageService::class)->delete($usage);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
        ->where('entity_id', $session->id)
        ->where('action', AuditLog::ACTION_REVERSAL)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($manager->id)
        ->and($auditLog?->patient_id)->toBe($patient->id)
        ->and($auditLog?->branch_id)->toBe($branch->id)
        ->and($auditLog?->metadata['trigger'] ?? null)->toBe('treatment_material_usage_reversed')
        ->and($auditLog?->metadata['reversed_treatment_material_id'] ?? null)->toBe($usage->id)
        ->and($auditLog?->metadata['material_name'] ?? null)->toBe('Gạc nha khoa')
        ->and($auditLog?->metadata['batch_number'] ?? null)->toBe('LOT-TM-005');
});

it('keeps treatment material usage reversal idempotent under retry stress', function (): void {
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
        'stock_qty' => 12,
        'cost_price' => 18_000,
        'name' => 'Composite flowable',
        'unit' => 'ống',
    ]);
    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-TM-RETRY-001',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 12,
        'purchase_price' => 18_000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $usage = app(TreatmentMaterialUsageService::class)->create([
        'treatment_session_id' => $session->id,
        'material_id' => $material->id,
        'batch_id' => $batch->id,
        'quantity' => 4,
    ]);

    $usageId = (int) $usage->id;
    $managerId = (int) $manager->id;

    $tasks = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static function () use ($managerId, $usageId): bool {
            auth()->loginUsingId($managerId);

            $retryUsage = new TreatmentMaterial;
            $retryUsage->forceFill(['id' => $usageId]);

            app(TreatmentMaterialUsageService::class)->delete($retryUsage);

            return true;
        };
    }

    Concurrency::driver('sync')->run($tasks);

    expect(TreatmentMaterial::query()->find($usageId))->toBeNull()
        ->and($material->fresh()->stock_qty)->toBe(12)
        ->and($batch->fresh()->quantity)->toBe(12)
        ->and(InventoryTransaction::query()
            ->where('treatment_session_id', $session->id)
            ->where('material_batch_id', $batch->id)
            ->where('type', 'adjust')
            ->where('note', 'Auto: revert usage delete')
            ->count())->toBe(1)
        ->and(AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
            ->where('entity_id', $session->id)
            ->where('action', AuditLog::ACTION_REVERSAL)
            ->where('metadata->trigger', 'treatment_material_usage_reversed')
            ->where('metadata->reversed_treatment_material_id', $usageId)
            ->count())->toBe(1);
});
