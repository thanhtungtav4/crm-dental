<?php

use App\Filament\Pages\Reports\FactoryStatistical;
use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\FactoryOrderItem;
use App\Models\Patient;
use App\Models\Supplier;
use App\Models\User;

it('queries factory orders instead of treatment sessions for the factory statistical report', function (): void {
    $page = app(FactoryStatistical::class);

    $queryMethod = new ReflectionMethod($page, 'getTableQuery');
    $queryMethod->setAccessible(true);
    $query = $queryMethod->invoke($page);

    expect($query->toSql())
        ->toContain('factory_orders')
        ->not->toContain('treatment_sessions');
});

it('computes factory report stats from factory orders within actor branch scope', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $supplier = Supplier::query()->create([
        'name' => 'Labo Report',
        'code' => 'LABOREPORT',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $patientA1 = Patient::factory()->create(['first_branch_id' => $branchA->id]);
    $patientA2 = Patient::factory()->create(['first_branch_id' => $branchA->id]);
    $patientB = Patient::factory()->create(['first_branch_id' => $branchB->id]);

    $orderedOrder = FactoryOrder::query()->create([
        'patient_id' => $patientA1->id,
        'branch_id' => $branchA->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);
    FactoryOrderItem::query()->create([
        'factory_order_id' => $orderedOrder->id,
        'item_name' => 'Rang su zirconia',
        'quantity' => 1,
        'unit_price' => 1250000,
        'status' => 'ordered',
    ]);
    FactoryOrder::runWithinManagedWorkflow(function () use ($orderedOrder): void {
        $orderedOrder->forceFill([
            'status' => FactoryOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ])->save();
    });

    $deliveredOrder = FactoryOrder::query()->create([
        'patient_id' => $patientA2->id,
        'branch_id' => $branchA->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);
    FactoryOrderItem::query()->create([
        'factory_order_id' => $deliveredOrder->id,
        'item_name' => 'Mao tam',
        'quantity' => 2,
        'unit_price' => 500000,
        'status' => 'ordered',
    ]);
    FactoryOrder::runWithinManagedWorkflow(function () use ($deliveredOrder): void {
        $deliveredOrder->forceFill([
            'status' => FactoryOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ])->save();
    });
    FactoryOrder::runWithinManagedWorkflow(function () use ($deliveredOrder): void {
        $deliveredOrder->forceFill([
            'status' => FactoryOrder::STATUS_DELIVERED,
            'delivered_at' => now(),
        ])->save();
    });

    $hiddenOrder = FactoryOrder::query()->create([
        'patient_id' => $patientB->id,
        'branch_id' => $branchB->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);
    FactoryOrderItem::query()->create([
        'factory_order_id' => $hiddenOrder->id,
        'item_name' => 'Khay tach nuou',
        'quantity' => 1,
        'unit_price' => 3000000,
        'status' => 'ordered',
    ]);

    $this->actingAs($manager);

    $stats = app(FactoryStatistical::class)->getStats();

    expect($stats)->toBe([
        ['label' => 'Tổng lệnh labo', 'value' => '2'],
        ['label' => 'Đang xử lý', 'value' => '1'],
        ['label' => 'Đã giao', 'value' => '1'],
        ['label' => 'Tổng giá trị', 'value' => '2,250,000 đ'],
    ]);
});

it('limits factory statistical branch filter options to accessible branches for managers', function (): void {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    $page = app(FactoryStatistical::class);
    $filtersMethod = new ReflectionMethod($page, 'getTableFilters');
    $filtersMethod->setAccessible(true);
    $filters = $filtersMethod->invoke($page);
    $branchFilter = collect($filters)
        ->first(fn ($filter) => method_exists($filter, 'getName') && $filter->getName() === 'branch_id');

    expect($branchFilter)->not->toBeNull();

    $options = $branchFilter->getOptions();

    expect($options)->toBe([
        $branchA->id => $branchA->name,
    ])
        ->and($options)->not->toHaveKey($branchB->id);
});
