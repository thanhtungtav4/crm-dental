<?php

use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\Supplier;
use App\Services\FactoryOrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('generates incrementing factory order numbers per day using a locked sequence row', function (): void {
    $generator = app(FactoryOrderNumberGenerator::class);

    $first = $generator->next(CarbonImmutable::parse('2026-03-06 09:00:00'));
    $second = $generator->next(CarbonImmutable::parse('2026-03-06 09:30:00'));
    $nextDay = $generator->next(CarbonImmutable::parse('2026-03-07 08:00:00'));

    expect($first)->toBe('LAB-20260306-0001')
        ->and($second)->toBe('LAB-20260306-0002')
        ->and($nextDay)->toBe('LAB-20260307-0001')
        ->and(DB::table('factory_order_sequences')->where('sequence_date', '2026-03-06')->value('last_number'))->toBe(2)
        ->and(DB::table('factory_order_sequences')->where('sequence_date', '2026-03-07')->value('last_number'))->toBe(1);
});

it('creates factory orders with unique generated order numbers and persists sequence state', function (): void {
    $branch = Branch::factory()->create(['active' => true]);
    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $supplier = Supplier::query()->create([
        'name' => 'Labo Sequence',
        'code' => 'LABSEQ',
        'payment_terms' => '30_days',
        'active' => true,
    ]);

    $firstOrder = FactoryOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $secondOrder = FactoryOrder::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    expect($firstOrder->order_no)->not->toBe($secondOrder->order_no)
        ->and($firstOrder->order_no)->toStartWith('LAB-')
        ->and($secondOrder->order_no)->toStartWith('LAB-')
        ->and(DB::table('factory_order_sequences')->count())->toBe(1)
        ->and(DB::table('factory_order_sequences')->value('last_number'))->toBe(2);
});

it('wires factory order creation through a transaction-safe generator boundary', function (): void {
    $createPage = File::get(app_path('Filament/Resources/FactoryOrders/Pages/CreateFactoryOrder.php'));
    $model = File::get(app_path('Models/FactoryOrder.php'));
    $service = File::get(app_path('Services/FactoryOrderNumberGenerator.php'));

    expect($createPage)
        ->toContain('handleRecordCreation')
        ->toContain('DB::transaction');

    expect($model)
        ->toContain('FactoryOrderNumberGenerator::class')
        ->toContain('return app(FactoryOrderNumberGenerator::class)->next();');

    expect($service)
        ->toContain('factory_order_sequences')
        ->toContain('lockForUpdate()')
        ->toContain('insertOrIgnore');
});
