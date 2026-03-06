<?php

use App\Models\Branch;
use App\Models\Material;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes material sku before saving', function (): void {
    $branch = Branch::factory()->create();

    $material = Material::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Composite resin',
        'sku' => '  inv-001  ',
        'unit' => 'hop',
        'stock_qty' => 0,
        'sale_price' => 100_000,
        'min_stock' => 0,
    ]);

    expect($material->fresh()->sku)->toBe('INV-001');
});

it('enforces unique sku at the database layer within the same branch only', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    Material::query()->create([
        'branch_id' => $branchA->id,
        'name' => 'Vat tu A',
        'sku' => 'INV-UNIQUE-001',
        'unit' => 'hop',
        'stock_qty' => 0,
        'sale_price' => 120_000,
        'min_stock' => 0,
    ]);

    expect(fn () => Material::query()->create([
        'branch_id' => $branchA->id,
        'name' => 'Vat tu A duplicate',
        'sku' => 'INV-UNIQUE-001',
        'unit' => 'hop',
        'stock_qty' => 0,
        'sale_price' => 125_000,
        'min_stock' => 0,
    ]))->toThrow(QueryException::class);

    $materialBranchB = Material::query()->create([
        'branch_id' => $branchB->id,
        'name' => 'Vat tu B',
        'sku' => 'INV-UNIQUE-001',
        'unit' => 'hop',
        'stock_qty' => 0,
        'sale_price' => 125_000,
        'min_stock' => 0,
    ]);

    expect($materialBranchB->sku)->toBe('INV-UNIQUE-001');
});

it('keeps stock quantity read only in the material form and page handlers', function (): void {
    $formSource = file_get_contents(app_path('Filament/Resources/Materials/Schemas/MaterialForm.php'));
    $createPageSource = file_get_contents(app_path('Filament/Resources/Materials/Pages/CreateMaterial.php'));
    $editPageSource = file_get_contents(app_path('Filament/Resources/Materials/Pages/EditMaterial.php'));

    expect($formSource)->toContain("TextInput::make('stock_qty')")
        ->toContain('->disabled()')
        ->toContain('->dehydrated(false)')
        ->and($createPageSource)->toContain("unset(\$data['stock_qty']);")
        ->and($editPageSource)->toContain("unset(\$data['stock_qty']);");
});
