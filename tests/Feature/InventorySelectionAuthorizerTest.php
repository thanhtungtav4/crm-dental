<?php

use App\Models\Branch;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventorySelectionAuthorizer;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('scopes inventory material options to accessible branches', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $materialA = Material::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Vat tu branch A',
    ]);

    $materialB = Material::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Vat tu branch B',
    ]);

    $options = app(InventorySelectionAuthorizer::class)->materialOptions($manager);

    expect($options)->toHaveKey($materialA->id)
        ->and($options[$materialA->id])->toBe('Vat tu branch A')
        ->and($options)->not->toHaveKey($materialB->id);
});

it('rejects cross branch inventory payloads for material and batch forms', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $foreignMaterial = Material::factory()->create([
        'branch_id' => $branchB->id,
    ]);

    expect(fn () => app(InventorySelectionAuthorizer::class)->sanitizeMaterialFormData($manager, [
        'branch_id' => $branchB->id,
        'sku' => 'INV-004',
        'name' => 'Vat tu sai chi nhanh',
    ]))->toThrow(ValidationException::class, 'chi nhánh ngoài phạm vi');

    expect(fn () => app(InventorySelectionAuthorizer::class)->sanitizeMaterialBatchFormData($manager, [
        'material_id' => $foreignMaterial->id,
        'batch_number' => 'LOT-FOREIGN',
    ]))->toThrow(ValidationException::class, 'Vật tư được chọn không thuộc phạm vi chi nhánh');
});

it('rejects inactive suppliers during inventory payload sanitization', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $inactiveSupplier = Supplier::query()->create([
        'name' => 'NCC Ngung hoat dong',
        'code' => 'SUPINV004',
        'active' => false,
    ]);

    expect(fn () => app(InventorySelectionAuthorizer::class)->sanitizeMaterialBatchFormData($manager, [
        'material_id' => $material->id,
        'supplier_id' => $inactiveSupplier->id,
        'batch_number' => 'LOT-INACTIVE',
    ]))->toThrow(ValidationException::class, 'nhà cung cấp đang hoạt động');
});

it('wires inventory forms and pages through branch-aware selection guards', function (): void {
    $materialForm = File::get(app_path('Filament/Resources/Materials/Schemas/MaterialForm.php'));
    $materialBatchForm = File::get(app_path('Filament/Resources/MaterialBatches/Schemas/MaterialBatchForm.php'));
    $createMaterialPage = File::get(app_path('Filament/Resources/Materials/Pages/CreateMaterial.php'));
    $editMaterialPage = File::get(app_path('Filament/Resources/Materials/Pages/EditMaterial.php'));
    $createMaterialBatchPage = File::get(app_path('Filament/Resources/MaterialBatches/Pages/CreateMaterialBatch.php'));
    $editMaterialBatchPage = File::get(app_path('Filament/Resources/MaterialBatches/Pages/EditMaterialBatch.php'));

    expect($materialForm)
        ->toContain('BranchAccess::scopeBranchQueryForCurrentUser')
        ->toContain('scopeActiveSuppliers');

    expect($materialBatchForm)
        ->toContain('scopeMaterials($query, auth()->user())')
        ->toContain('scopeActiveSuppliers');

    expect($createMaterialPage)
        ->toContain('sanitizeMaterialFormData');

    expect($editMaterialPage)
        ->toContain('sanitizeMaterialFormData');

    expect($createMaterialBatchPage)
        ->toContain('sanitizeMaterialBatchFormData');

    expect($editMaterialBatchPage)
        ->toContain('sanitizeMaterialBatchFormData');
});
