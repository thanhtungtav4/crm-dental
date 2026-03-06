<?php

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\DoctorBranchAssignment;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('scopes branch resource queries to accessible branches for delegated viewers', function (): void {
    $accessibleBranch = Branch::factory()->create(['active' => true]);
    $hiddenBranch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $accessibleBranch->id,
    ]);
    $manager->assignRole('Manager');

    Permission::findOrCreate('ViewAny:Branch', 'web');
    Permission::findOrCreate('View:Branch', 'web');
    $manager->givePermissionTo(['ViewAny:Branch', 'View:Branch']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager);

    expect(BranchResource::getEloquentQuery()->pluck('branches.id')->all())
        ->toContain($accessibleBranch->id)
        ->not->toContain($hiddenBranch->id);

    expect($manager->can('view', $accessibleBranch))->toBeTrue()
        ->and($manager->can('view', $hiddenBranch))->toBeFalse()
        ->and(BranchResource::getRecordRouteBindingEloquentQuery()->whereKey($accessibleBranch->id)->exists())->toBeTrue()
        ->and(BranchResource::getRecordRouteBindingEloquentQuery()->whereKey($hiddenBranch->id)->exists())->toBeFalse();
});

it('scopes user resource queries to accessible primary and assigned branches', function (): void {
    $accessibleBranch = Branch::factory()->create(['active' => true]);
    $hiddenBranch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $accessibleBranch->id,
    ]);
    $manager->assignRole('Manager');

    Permission::findOrCreate('ViewAny:User', 'web');
    Permission::findOrCreate('View:User', 'web');
    $manager->givePermissionTo(['ViewAny:User', 'View:User']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $visiblePrimaryUser = User::factory()->create([
        'branch_id' => $accessibleBranch->id,
    ]);

    $visibleAssignedDoctor = User::factory()->create([
        'branch_id' => $hiddenBranch->id,
    ]);
    $visibleAssignedDoctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $visibleAssignedDoctor->id,
        'branch_id' => $accessibleBranch->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->subDay()->toDateString(),
    ]);

    $hiddenUser = User::factory()->create([
        'branch_id' => $hiddenBranch->id,
    ]);

    $this->actingAs($manager);

    $visibleIds = UserResource::getEloquentQuery()->pluck('users.id')->all();

    expect($visibleIds)
        ->toContain($visiblePrimaryUser->id)
        ->toContain($visibleAssignedDoctor->id)
        ->not->toContain($hiddenUser->id);

    expect($manager->can('view', $visiblePrimaryUser))->toBeTrue()
        ->and($manager->can('view', $visibleAssignedDoctor))->toBeTrue()
        ->and($manager->can('view', $hiddenUser))->toBeFalse()
        ->and(UserResource::getRecordRouteBindingEloquentQuery()->whereKey($visibleAssignedDoctor->id)->exists())->toBeTrue()
        ->and(UserResource::getRecordRouteBindingEloquentQuery()->whereKey($hiddenUser->id)->exists())->toBeFalse();
});
