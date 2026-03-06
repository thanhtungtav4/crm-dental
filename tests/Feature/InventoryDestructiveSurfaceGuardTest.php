<?php

use App\Models\Branch;
use App\Models\Material;
use App\Models\MaterialBatch;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive inventory surfaces from material and batch ui', function (): void {
    $materialEditPage = File::get(app_path('Filament/Resources/Materials/Pages/EditMaterial.php'));
    $materialsTable = File::get(app_path('Filament/Resources/Materials/Tables/MaterialsTable.php'));
    $materialBatchEditPage = File::get(app_path('Filament/Resources/MaterialBatches/Pages/EditMaterialBatch.php'));
    $materialBatchesTable = File::get(app_path('Filament/Resources/MaterialBatches/Tables/MaterialBatchesTable.php'));
    $relationManager = File::get(app_path('Filament/Resources/Materials/RelationManagers/BatchesRelationManager.php'));

    expect($materialEditPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($materialsTable)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');

    expect($materialBatchEditPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()')
        ->toContain("unset(\$data['quantity'], \$data['status']);");

    expect($materialBatchesTable)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');

    expect($relationManager)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('DeleteBulkAction::make()');
});

it('locks batch quantity and status mutation behind create-only form dehydration guards', function (): void {
    $materialBatchForm = File::get(app_path('Filament/Resources/MaterialBatches/Schemas/MaterialBatchForm.php'));
    $relationManager = File::get(app_path('Filament/Resources/Materials/RelationManagers/BatchesRelationManager.php'));

    expect($materialBatchForm)
        ->toContain('->disabled(fn (?Model $record): bool => $record !== null)')
        ->toContain('->dehydrated(fn (?Model $record): bool => $record === null)')
        ->toContain('workflow inventory');

    expect($relationManager)
        ->toContain('->disabled(fn (?Model $record): bool => $record !== null)')
        ->toContain('->dehydrated(fn (?Model $record): bool => $record === null)')
        ->toContain('workflow inventory');
});

it('denies material and material batch delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create(['active' => true]);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $material = Material::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $materialBatch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-INV-003',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'quantity' => 20,
        'purchase_price' => 15000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
        'created_by' => $admin->id,
    ]);

    expect($admin->can('delete', $material))->toBeFalse()
        ->and($admin->can('restore', $material))->toBeFalse()
        ->and($admin->can('forceDelete', $material))->toBeFalse()
        ->and($admin->can('delete', $materialBatch))->toBeFalse()
        ->and($admin->can('restore', $materialBatch))->toBeFalse()
        ->and($admin->can('forceDelete', $materialBatch))->toBeFalse();
});

it('blocks direct material and batch delete attempts at model layer', function (): void {
    $material = Material::factory()->create();

    $materialBatch = MaterialBatch::query()->create([
        'material_id' => $material->id,
        'batch_number' => 'LOT-INV-MODEL',
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'quantity' => 12,
        'purchase_price' => 12000,
        'received_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    expect(fn () => $material->delete())
        ->toThrow(ValidationException::class, 'khong ho tro xoa');

    expect(fn () => $materialBatch->delete())
        ->toThrow(ValidationException::class, 'khong ho tro xoa');
});
