<?php

use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FactoryOrderAuthorizer;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('requires an active canonical supplier and syncs vendor snapshot on create', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Labo Canonical',
        'code' => 'LABOCANON',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $this->actingAs($manager);

    $data = app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($manager, [
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $order = FactoryOrder::query()->create($data);

    expect($order->supplier_id)->toBe($supplier->id)
        ->and($order->vendor_name)->toBe('Labo Canonical')
        ->and($order->supplier?->is($supplier))->toBeTrue();
});

it('rejects selecting an inactive supplier for a new factory order', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create([
        'first_branch_id' => $branch->id,
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Labo Inactive',
        'code' => 'LABOINACTIVE',
        'payment_terms' => '30_days',
        'active' => false,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($manager, [
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]))->toThrow(ValidationException::class, 'Chỉ được chọn nhà cung cấp đang hoạt động.');
});

it('wires supplier relationship into the factory order resource surfaces', function (): void {
    $form = File::get(app_path('Filament/Resources/FactoryOrders/Schemas/FactoryOrderForm.php'));
    $table = File::get(app_path('Filament/Resources/FactoryOrders/Tables/FactoryOrdersTable.php'));
    $resource = File::get(app_path('Filament/Resources/FactoryOrders/FactoryOrderResource.php'));
    $model = File::get(app_path('Models/FactoryOrder.php'));

    expect($form)
        ->toContain("Select::make('supplier_id')")
        ->not->toContain("TextInput::make('vendor_name')");

    expect($table)
        ->toContain("TextColumn::make('supplier.name')")
        ->toContain("SelectFilter::make('supplier_id')");

    expect($resource)
        ->toContain("'supplier'");

    expect($model)
        ->toContain('return $this->belongsTo(Supplier::class);')
        ->toContain('Vui lòng chọn nhà cung cấp cho lệnh labo.');
});
