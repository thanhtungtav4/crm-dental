<?php

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive supplier surfaces from the ui', function (): void {
    $editPage = File::get(app_path('Filament/Resources/Suppliers/Pages/EditSupplier.php'));
    $table = File::get(app_path('Filament/Resources/Suppliers/Tables/SuppliersTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');
});

it('denies supplier delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create(['active' => true]);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $supplier = Supplier::query()->create([
        'name' => 'Supplier guard',
        'code' => 'SUPGUARD01',
        'active' => true,
    ]);

    expect($admin->can('delete', $supplier))->toBeFalse()
        ->and($admin->can('deleteAny', Supplier::class))->toBeFalse()
        ->and($admin->can('restore', $supplier))->toBeFalse()
        ->and($admin->can('forceDelete', $supplier))->toBeFalse();
});

it('blocks direct supplier delete attempts at model layer', function (): void {
    $supplier = Supplier::query()->create([
        'name' => 'Supplier model guard',
        'code' => 'SUPGUARD02',
        'active' => true,
    ]);

    expect(fn () => $supplier->delete())
        ->toThrow(ValidationException::class, 'khong ho tro xoa');
});
