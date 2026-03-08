<?php

use App\Models\Material;
use App\Models\MaterialBatch;
use App\Services\InventoryMutationService;
use Database\Seeders\InventoryScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

it('creates inventory scenarios for low-stock and expired-batch handling', function (): void {
    seed(LocalDemoDataSeeder::class);

    $material = Material::query()
        ->where('sku', InventoryScenarioSeeder::MATERIAL_SKU)
        ->firstOrFail();
    $activeBatch = MaterialBatch::query()
        ->where('batch_number', InventoryScenarioSeeder::ACTIVE_BATCH_NUMBER)
        ->firstOrFail();
    $expiredBatch = MaterialBatch::query()
        ->where('batch_number', InventoryScenarioSeeder::EXPIRED_BATCH_NUMBER)
        ->firstOrFail();

    expect($material->isLowStock())->toBeTrue()
        ->and($material->needsReorder())->toBeTrue();

    $mutation = app(InventoryMutationService::class)->consumeBatch(
        materialId: $material->id,
        batchId: $activeBatch->id,
        quantity: 5,
        expectedBranchId: $material->branch_id,
    );

    expect($mutation['material']->stock_qty)->toBe(4)
        ->and($mutation['batch']->status)->toBe('depleted');

    expect(fn () => app(InventoryMutationService::class)->consumeBatch(
        materialId: $material->id,
        batchId: $expiredBatch->id,
        quantity: 1,
        expectedBranchId: $material->branch_id,
    ))->toThrow(ValidationException::class, 'Khong duoc su dung lo vat tu da het han.');
});
