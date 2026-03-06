<?php

use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Services\InventoryMutationService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('consumes inventory through the canonical mutation boundary and marks depleted batches', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 5,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-INV-005-CONSUME',
        'expiry_date' => now()->addMonths(2)->toDateString(),
        'quantity' => 5,
        'purchase_price' => 12000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $mutation = app(InventoryMutationService::class)->consumeBatch(
        materialId: $material->id,
        batchId: $batch->id,
        quantity: 5,
        expectedBranchId: $branch->id,
    );

    expect($mutation['material']->stock_qty)->toBe(0)
        ->and($mutation['batch']->quantity)->toBe(0)
        ->and($mutation['batch']->status)->toBe('depleted')
        ->and($material->fresh()->stock_qty)->toBe(0)
        ->and($batch->fresh()->quantity)->toBe(0)
        ->and($batch->fresh()->status)->toBe('depleted');
});

it('restores inventory through the canonical mutation boundary and revives depleted batches', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
        'stock_qty' => 0,
    ]);

    $batch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-INV-005-RESTORE',
        'expiry_date' => now()->addMonths(2)->toDateString(),
        'quantity' => 0,
        'purchase_price' => 12000,
        'received_date' => now()->toDateString(),
        'status' => 'depleted',
    ]);

    $mutation = app(InventoryMutationService::class)->restoreBatch(
        materialId: $material->id,
        batchId: $batch->id,
        quantity: 3,
        expectedBranchId: $branch->id,
    );

    expect($mutation['material']->stock_qty)->toBe(3)
        ->and($mutation['batch']->quantity)->toBe(3)
        ->and($mutation['batch']->status)->toBe('active')
        ->and($material->fresh()->stock_qty)->toBe(3)
        ->and($batch->fresh()->quantity)->toBe(3)
        ->and($batch->fresh()->status)->toBe('active');
});

it('rejects branch mismatches and invalid batch material pairings in the mutation boundary', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $materialA = Material::factory()->create([
        'branch_id' => $branchA->id,
        'stock_qty' => 5,
    ]);

    $materialB = Material::factory()->create([
        'branch_id' => $branchB->id,
        'stock_qty' => 5,
    ]);

    $batchB = MaterialBatch::query()->create([
        'material_id' => $materialB->id,
        'batch_number' => 'LOT-INV-005-MISMATCH',
        'expiry_date' => now()->addMonths(2)->toDateString(),
        'quantity' => 5,
        'purchase_price' => 9000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect(fn () => app(InventoryMutationService::class)->consumeBatch(
        materialId: $materialA->id,
        batchId: $batchB->id,
        quantity: 1,
        expectedBranchId: $branchA->id,
    ))->toThrow(ValidationException::class, 'Lo vat tu khong thuoc vat tu da chon.');

    expect(fn () => app(InventoryMutationService::class)->consumeBatch(
        materialId: $materialB->id,
        batchId: $batchB->id,
        quantity: 1,
        expectedBranchId: $branchA->id,
    ))->toThrow(ValidationException::class, 'Vat tu khong cung chi nhanh');
});

it('routes treatment usage issue note and batch helpers through inventory mutation service', function (): void {
    $treatmentUsageService = File::get(app_path('Services/TreatmentMaterialUsageService.php'));
    $materialIssueNote = File::get(app_path('Models/MaterialIssueNote.php'));
    $materialBatch = File::get(app_path('Models/MaterialBatch.php'));

    expect($treatmentUsageService)
        ->toContain('InventoryMutationService::class')
        ->toContain('->consumeBatch(')
        ->toContain('->restoreBatch(');

    expect($materialIssueNote)
        ->toContain('InventoryMutationService::class')
        ->toContain('->consumeBatch(');

    expect($materialBatch)
        ->toContain('InventoryMutationService::class')
        ->toContain('->consumeBatch(')
        ->toContain('->restoreBatch(');
});
