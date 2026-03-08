<?php

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\GovernanceScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\seed;

it('creates governance scenarios for delegated branch-scoped visibility', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()->where('email', 'manager.q1@demo.nhakhoaanphuc.test')->firstOrFail();
    $assignedDoctor = User::query()->where('email', GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)->firstOrFail();
    $hiddenUser = User::query()->where('email', GovernanceScenarioSeeder::HIDDEN_USER_EMAIL)->firstOrFail();

    Permission::findOrCreate('ViewAny:Branch', 'web');
    Permission::findOrCreate('View:Branch', 'web');
    Permission::findOrCreate('ViewAny:User', 'web');
    Permission::findOrCreate('View:User', 'web');
    $manager->givePermissionTo(['ViewAny:Branch', 'View:Branch', 'ViewAny:User', 'View:User']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($manager);

    $visibleUserIds = UserResource::getEloquentQuery()->pluck('users.id')->all();
    $visibleBranchIds = BranchResource::getEloquentQuery()->pluck('branches.id')->all();

    expect($visibleUserIds)->toContain($assignedDoctor->id)
        ->not->toContain($hiddenUser->id)
        ->and($visibleBranchIds)->toContain($manager->branch_id)
        ->not->toContain($hiddenUser->branch_id)
        ->and($manager->can('view', $assignedDoctor))->toBeTrue()
        ->and($manager->can('view', $hiddenUser))->toBeFalse();
});
