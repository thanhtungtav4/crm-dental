<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guardName = (string) config('auth.defaults.guard', 'web');
        $permissionName = 'View:IntegrationSettings';

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => $guardName,
        ]);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'Admin',
            'guard_name' => $guardName,
        ]);

        if (! $adminRole->hasPermissionTo($permission)) {
            $adminRole->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
