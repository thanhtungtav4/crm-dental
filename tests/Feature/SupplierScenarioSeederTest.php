<?php

use App\Models\FactoryOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FactoryOrderAuthorizer;
use App\Services\FactoryOrderWorkflowService;
use Database\Seeders\LocalDemoDataSeeder;
use Database\Seeders\SupplierScenarioSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

it('creates supplier scenarios for workflow progression and inactive supplier rejection', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.nhakhoaanphuc.test')->firstOrFail();
    $this->actingAs($manager);

    $order = FactoryOrder::query()->where('order_no', SupplierScenarioSeeder::FACTORY_ORDER_NO)->firstOrFail();
    $inactiveSupplier = Supplier::query()->where('code', SupplierScenarioSeeder::INACTIVE_SUPPLIER_CODE)->firstOrFail();

    $workflow = app(FactoryOrderWorkflowService::class);

    $ordered = $workflow->markOrdered($order);
    $inProgress = $workflow->markInProgress($ordered);
    $delivered = $workflow->markDelivered($inProgress);

    expect($delivered->status)->toBe(FactoryOrder::STATUS_DELIVERED)
        ->and($delivered->items()->value('status'))->toBe('delivered');

    expect(fn () => app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData($manager, [
        'patient_id' => $order->patient_id,
        'branch_id' => $order->branch_id,
        'supplier_id' => $inactiveSupplier->id,
        'doctor_id' => $order->doctor_id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]))->toThrow(ValidationException::class, 'Chỉ được chọn nhà cung cấp đang hoạt động.');
});
