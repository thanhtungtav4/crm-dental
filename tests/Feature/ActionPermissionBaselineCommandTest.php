<?php

use App\Support\ActionPermission;
use App\Support\SensitiveActionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('fails when action permission baseline drifts', function () {
    Permission::query()
        ->where('name', ActionPermission::AUTOMATION_RUN)
        ->delete();

    $this->artisan('security:assert-action-permission-baseline')
        ->expectsOutputToContain('Missing permission: '.ActionPermission::AUTOMATION_RUN)
        ->assertExitCode(1);
});

it('sync option repairs action permission baseline and passes', function () {
    Permission::query()
        ->where('name', ActionPermission::PATIENT_BRANCH_TRANSFER)
        ->delete();

    $doctorRole = Role::query()->where('name', 'Doctor')->firstOrFail();
    $paymentReversal = Permission::query()
        ->where('name', ActionPermission::PAYMENT_REVERSAL)
        ->firstOrFail();
    $doctorRole->givePermissionTo($paymentReversal);

    $this->artisan('security:assert-action-permission-baseline', ['--sync' => true])
        ->expectsOutputToContain('Action permission baseline: OK.')
        ->assertSuccessful();

    foreach (ActionPermission::all() as $permissionName) {
        $permission = Permission::query()->where('name', $permissionName)->first();

        expect($permission)->not->toBeNull("Thiếu permission {$permissionName} sau khi sync");
    }

    foreach (SensitiveActionRegistry::roleMatrix() as $permissionName => $allowedRoles) {
        foreach (['Admin', 'Manager', 'Doctor', 'CSKH'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->firstOrFail();

            expect($role->hasPermissionTo($permissionName))
                ->toBe(in_array($roleName, $allowedRoles, true));
        }
    }
});
