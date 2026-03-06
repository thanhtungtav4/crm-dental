<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('fails when governance resource baseline drifts', function () {
    $managerRole = Role::query()->where('name', 'Manager')->firstOrFail();
    $branchPermission = Permission::query()->where('name', 'Create:Branch')->firstOrFail();

    $managerRole->givePermissionTo($branchPermission);

    $this->artisan('security:assert-governance-resource-baseline')
        ->expectsOutputToContain('Role matrix mismatch: permission=Create:Branch')
        ->assertExitCode(1);
});

it('sync option repairs governance resource baseline and passes', function () {
    Permission::query()
        ->where('name', 'Delete:Role')
        ->delete();

    $managerRole = Role::query()->where('name', 'Manager')->firstOrFail();
    $userPermission = Permission::query()->where('name', 'Create:User')->firstOrFail();
    $managerRole->givePermissionTo($userPermission);

    $this->artisan('security:assert-governance-resource-baseline', ['--sync' => true])
        ->expectsOutputToContain('Governance resource permission baseline: OK.')
        ->assertSuccessful();

    $managerRole = $managerRole->fresh();
    $adminRole = Role::query()->where('name', 'Admin')->firstOrFail();

    expect(Permission::query()->where('name', 'Delete:Role')->exists())->toBeTrue()
        ->and($managerRole?->hasPermissionTo('Create:Branch'))->toBeFalse()
        ->and($managerRole?->hasPermissionTo('Create:User'))->toBeFalse()
        ->and($adminRole->hasPermissionTo('Create:Branch'))->toBeTrue()
        ->and($adminRole->hasPermissionTo('Delete:Role'))->toBeTrue();
});
