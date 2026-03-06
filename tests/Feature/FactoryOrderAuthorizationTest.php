<?php

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('locks factory order resource access to admin and manager with branch scoped records', function (): void {
    $accessibleBranch = Branch::factory()->create(['active' => true]);
    $hiddenBranch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create(['branch_id' => $accessibleBranch->id]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create(['branch_id' => $accessibleBranch->id]);
    $doctor->assignRole('Doctor');

    $admin = User::factory()->create(['branch_id' => $accessibleBranch->id]);
    $admin->assignRole('Admin');

    $visibleOrder = FactoryOrder::query()->create([
        'patient_id' => Patient::factory()->create(['first_branch_id' => $accessibleBranch->id])->id,
        'branch_id' => $accessibleBranch->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $hiddenOrder = FactoryOrder::query()->create([
        'patient_id' => Patient::factory()->create(['first_branch_id' => $hiddenBranch->id])->id,
        'branch_id' => $hiddenBranch->id,
        'status' => FactoryOrder::STATUS_DRAFT,
        'priority' => 'normal',
    ]);

    $this->actingAs($manager);

    expect(FactoryOrderResource::canViewAny())->toBeTrue()
        ->and(FactoryOrderResource::canCreate())->toBeTrue()
        ->and($manager->can('view', $visibleOrder))->toBeTrue()
        ->and($manager->can('view', $hiddenOrder))->toBeFalse()
        ->and($manager->can('delete', $visibleOrder))->toBeTrue()
        ->and($manager->can('deleteAny', FactoryOrder::class))->toBeFalse()
        ->and(FactoryOrderResource::getEloquentQuery()->pluck('factory_orders.id')->all())->toContain($visibleOrder->id)
        ->and(FactoryOrderResource::getEloquentQuery()->pluck('factory_orders.id')->all())->not->toContain($hiddenOrder->id)
        ->and(FactoryOrderResource::getRecordRouteBindingEloquentQuery()->whereKey($visibleOrder->id)->exists())->toBeTrue()
        ->and(FactoryOrderResource::getRecordRouteBindingEloquentQuery()->whereKey($hiddenOrder->id)->exists())->toBeFalse();

    $visibleOrder->update(['status' => FactoryOrder::STATUS_ORDERED]);

    expect($manager->can('delete', $visibleOrder))->toBeFalse();

    $this->actingAs($doctor);

    expect(FactoryOrderResource::canViewAny())->toBeFalse()
        ->and(FactoryOrderResource::canCreate())->toBeFalse()
        ->and($doctor->can('view', $visibleOrder))->toBeFalse()
        ->and($doctor->can('create', FactoryOrder::class))->toBeFalse();

    $this->actingAs($admin);

    expect(FactoryOrderResource::canViewAny())->toBeTrue()
        ->and(FactoryOrderResource::canCreate())->toBeTrue()
        ->and($admin->can('view', $visibleOrder))->toBeTrue()
        ->and($admin->can('view', $hiddenOrder))->toBeTrue();
});

it('guards patient workspace factory order links with policy checks', function (): void {
    $patientView = File::get(resource_path('views/filament/resources/patients/pages/view-patient.blade.php'));

    expect($patientView)
        ->toContain("@can('create', \\App\\Models\\FactoryOrder::class)")
        ->toContain("@can('update', \$order)");
});
