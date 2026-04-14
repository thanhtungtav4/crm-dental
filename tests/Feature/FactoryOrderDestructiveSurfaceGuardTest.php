<?php

use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes destructive factory order surfaces from the ui', function (): void {
    $editPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/EditFactoryOrder.php'));
    $table = File::get(app_path('Filament/Resources/FactoryOrders/Tables/FactoryOrdersTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('DeleteAction');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('DeleteBulkAction');
});

it('denies factory order delete restore and force delete via policy even for admin', function (): void {
    $branch = Branch::factory()->create(['active' => true]);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $supplier = Supplier::query()->create([
        'name' => 'Labo hard guard',
        'code' => 'LABO-HARD-GUARD',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $order = FactoryOrder::query()->create([
        'patient_id' => Patient::factory()->create(['first_branch_id' => $branch->id])->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    expect($admin->can('delete', $order))->toBeFalse()
        ->and($admin->can('deleteAny', FactoryOrder::class))->toBeFalse()
        ->and($admin->can('restore', $order))->toBeFalse()
        ->and($admin->can('forceDelete', $order))->toBeFalse();
});

it('blocks direct factory order delete attempts at model layer', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $supplier = Supplier::query()->create([
        'name' => 'Labo model guard',
        'code' => 'LABO-MODEL-GUARD',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $order = FactoryOrder::query()->create([
        'patient_id' => Patient::factory()->create(['first_branch_id' => $branch->id])->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    expect(fn () => $order->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
