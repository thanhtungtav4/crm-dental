<?php

use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FactoryOrderWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('routes factory order status transitions through workflow service and syncs item statuses', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branch->id,
        'is_active' => true,
        'is_primary' => true,
    ]);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $supplier = Supplier::query()->create([
        'name' => 'Labo Workflow',
        'code' => 'LABOWORK',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $order = FactoryOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'doctor_id' => $doctor->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $item = FactoryOrderItem::query()->create([
        'factory_order_id' => $order->id,
        'item_name' => 'Mao rang su',
        'quantity' => 1,
        'unit_price' => 1200000,
        'status' => 'ordered',
    ]);

    $this->actingAs($manager);

    $workflow = app(FactoryOrderWorkflowService::class);

    $orderedOrder = $workflow->markOrdered($order);
    expect($orderedOrder->status)->toBe(FactoryOrder::STATUS_ORDERED)
        ->and($orderedOrder->ordered_at)->not->toBeNull()
        ->and($item->fresh()->status)->toBe('ordered');

    $inProgressOrder = $workflow->markInProgress($orderedOrder);
    expect($inProgressOrder->status)->toBe(FactoryOrder::STATUS_IN_PROGRESS)
        ->and($item->fresh()->status)->toBe('in_progress');

    $deliveredOrder = $workflow->markDelivered($inProgressOrder);
    expect($deliveredOrder->status)->toBe(FactoryOrder::STATUS_DELIVERED)
        ->and($deliveredOrder->delivered_at)->not->toBeNull()
        ->and($item->fresh()->status)->toBe('delivered');
});

it('blocks direct status changes and item mutations once order leaves draft', function (): void {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $supplier = Supplier::query()->create([
        'name' => 'Labo Guard',
        'code' => 'LABOGUARD',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $order = FactoryOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $item = FactoryOrderItem::query()->create([
        'factory_order_id' => $order->id,
        'item_name' => 'Khay lay dau',
        'quantity' => 1,
        'unit_price' => 500000,
        'status' => 'ordered',
    ]);

    $this->actingAs($manager);

    $orderedOrder = app(FactoryOrderWorkflowService::class)->markOrdered($order);

    expect(fn () => $orderedOrder->update([
        'status' => FactoryOrder::STATUS_IN_PROGRESS,
    ]))->toThrow(ValidationException::class, 'Trang thai lenh labo chi duoc thay doi qua FactoryOrderWorkflowService.');

    expect(fn () => FactoryOrderItem::query()->create([
        'factory_order_id' => $orderedOrder->id,
        'item_name' => 'Wax-up',
        'quantity' => 1,
        'unit_price' => 250000,
        'status' => 'ordered',
    ]))->toThrow(ValidationException::class, 'Chi co the sua hang muc labo khi lenh dang o trang thai nhap.');

    expect(fn () => tap($item->fresh())->update([
        'quantity' => 2,
    ]))->toThrow(ValidationException::class, 'Chi co the sua hang muc labo khi lenh dang o trang thai nhap.');

    expect(fn () => $item->fresh()->delete())->toThrow(ValidationException::class, 'Chi co the sua hang muc labo khi lenh dang o trang thai nhap.');
});

it('wires factory order pages and relation manager through the workflow boundary', function (): void {
    $createPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/CreateFactoryOrder.php'));
    $editPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/EditFactoryOrder.php'));
    $table = File::get(app_path('Filament/Resources/FactoryOrders/Tables/FactoryOrdersTable.php'));
    $relationManager = File::get(app_path('Filament/Resources/FactoryOrders/RelationManagers/ItemsRelationManager.php'));
    $model = File::get(app_path('Models/FactoryOrder.php'));
    $itemModel = File::get(app_path('Models/FactoryOrderItem.php'));
    $service = File::get(app_path('Services/FactoryOrderWorkflowService.php'));

    expect($createPage)
        ->toContain('FactoryOrderWorkflowService::class')
        ->toContain('prepareCreatePayload')
        ->toContain('handleRecordCreation');

    expect($editPage)
        ->toContain('FactoryOrderWorkflowService::class')
        ->toContain('prepareEditablePayload');

    expect($table)
        ->toContain('FactoryOrderWorkflowService::class')
        ->toContain('markOrdered')
        ->toContain('markInProgress')
        ->toContain('markDelivered')
        ->toContain('cancel');

    expect($relationManager)
        ->toContain('canMutateItems')
        ->toContain('checkIfRecordIsSelectableUsing');

    expect($model)
        ->toContain('runWithinManagedWorkflow')
        ->toContain('Trang thai lenh labo chi duoc thay doi qua FactoryOrderWorkflowService.')
        ->toContain('return $this->status === self::STATUS_DRAFT;');

    expect($itemModel)
        ->toContain('assertItemsEditable');

    expect($service)
        ->toContain('lockForUpdate()')
        ->toContain('syncItemStatuses');
});
