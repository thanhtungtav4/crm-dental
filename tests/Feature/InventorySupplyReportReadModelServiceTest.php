<?php

use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Material;
use App\Models\Patient;
use App\Models\Supplier;
use App\Services\InventorySupplyReportReadModelService;

it('summarizes inventory and supplier reports within selected branches', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $supplier = Supplier::query()->create([
        'name' => 'Supplier Report',
        'code' => 'SUP-RPT',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    Material::query()->create([
        'branch_id' => $branchA->id,
        'name' => 'Composite A',
        'sku' => 'COMP-A',
        'unit' => 'hop',
        'stock_qty' => 2,
        'min_stock' => 5,
        'category' => 'dental_material',
        'supplier_id' => $supplier->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Material::query()->create([
        'branch_id' => $branchB->id,
        'name' => 'Composite B',
        'sku' => 'COMP-B',
        'unit' => 'hop',
        'stock_qty' => 10,
        'min_stock' => 5,
        'category' => 'dental_material',
        'supplier_id' => $supplier->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $patientA = Patient::factory()->create(['first_branch_id' => $branchA->id]);
    $patientB = Patient::factory()->create(['first_branch_id' => $branchB->id]);

    $factoryOrderA = FactoryOrder::query()->create([
        'patient_id' => $patientA->id,
        'branch_id' => $branchA->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    FactoryOrderItem::query()->create([
        'factory_order_id' => $factoryOrderA->id,
        'item_name' => 'Crown A',
        'quantity' => 1,
        'unit_price' => 900000,
        'total_price' => 900000,
        'status' => 'ordered',
    ]);
    FactoryOrder::runWithinManagedWorkflow(function () use ($factoryOrderA): void {
        $factoryOrderA->forceFill([
            'status' => FactoryOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ])->save();
    });

    $factoryOrderB = FactoryOrder::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    FactoryOrderItem::query()->create([
        'factory_order_id' => $factoryOrderB->id,
        'item_name' => 'Crown B',
        'quantity' => 1,
        'unit_price' => 1900000,
        'total_price' => 1900000,
        'status' => 'ordered',
    ]);
    FactoryOrder::runWithinManagedWorkflow(function () use ($factoryOrderB): void {
        $factoryOrderB->forceFill([
            'ordered_at' => now(),
            'status' => FactoryOrder::STATUS_ORDERED,
        ])->save();
    });
    FactoryOrder::runWithinManagedWorkflow(function () use ($factoryOrderB): void {
        $factoryOrderB->forceFill([
            'status' => FactoryOrder::STATUS_DELIVERED,
            'delivered_at' => now(),
        ])->save();
    });

    $service = app(InventorySupplyReportReadModelService::class);

    expect($service->materialInventorySummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe([
            'total_materials' => 1,
            'low_stock' => 1,
        ])
        ->and($service->factoryOrderSummary([$branchA->id], now()->toDateString(), now()->toDateString()))
        ->toBe([
            'total_orders' => 1,
            'open_orders' => 1,
            'delivered_orders' => 0,
            'total_value' => 900000.0,
        ]);
});

it('returns empty inventory and supplier readers for inaccessible branch selections', function (): void {
    $service = app(InventorySupplyReportReadModelService::class);

    expect($service->materialInventoryQuery([])->get())->toHaveCount(0)
        ->and($service->factoryOrderQuery([])->get())->toHaveCount(0)
        ->and($service->materialInventorySummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total_materials' => 0,
            'low_stock' => 0,
        ])
        ->and($service->factoryOrderSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total_orders' => 0,
            'open_orders' => 0,
            'delivered_orders' => 0,
            'total_value' => 0.0,
        ]);
});
