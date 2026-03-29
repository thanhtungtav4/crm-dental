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
        $permissionName = 'View:ConversationInbox';

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => $guardName,
        ]);

        foreach (['Admin', 'Manager', 'CSKH'] as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guardName,
            ]);

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
