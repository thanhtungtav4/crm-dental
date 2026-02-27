<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);

        $rolesTable = $tableNames['roles'] ?? 'roles';
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $guardName = (string) config('auth.defaults.guard', 'web');
        $now = now();

        $roleName = 'AutomationService';
        $permissionName = 'Action:AutomationRun';
        $automationEmail = 'automation.scheduler@local.invalid';

        $roleId = DB::table($rolesTable)
            ->where('name', $roleName)
            ->where('guard_name', $guardName)
            ->value('id');

        if (! $roleId) {
            $roleId = DB::table($rolesTable)->insertGetId([
                'name' => $roleName,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionId = DB::table($permissionsTable)
            ->where('name', $permissionName)
            ->where('guard_name', $guardName)
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($permissionsTable)->insertGetId([
                'name' => $permissionName,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table($roleHasPermissionsTable)->updateOrInsert([
            'permission_id' => (int) $permissionId,
            'role_id' => (int) $roleId,
        ], [
            'permission_id' => (int) $permissionId,
            'role_id' => (int) $roleId,
        ]);

        if (! Schema::hasTable('users')) {
            return;
        }

        $actorUserId = DB::table('users')
            ->where('email', $automationEmail)
            ->value('id');

        if (! $actorUserId) {
            $actorUserId = DB::table('users')->insertGetId([
                'name' => 'Automation Scheduler',
                'email' => $automationEmail,
                'email_verified_at' => $now,
                'password' => Hash::make(Str::random(64)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable($modelHasRolesTable)) {
            DB::table($modelHasRolesTable)->updateOrInsert([
                'role_id' => (int) $roleId,
                $modelMorphKey => (int) $actorUserId,
                'model_type' => \App\Models\User::class,
            ], [
                'role_id' => (int) $roleId,
                $modelMorphKey => (int) $actorUserId,
                'model_type' => \App\Models\User::class,
            ]);
        }

        if (! Schema::hasTable('clinic_settings')) {
            return;
        }

        DB::table('clinic_settings')->updateOrInsert([
            'key' => 'scheduler.automation_actor_user_id',
        ], [
            'group' => 'scheduler',
            'label' => 'Automation actor user ID',
            'value' => (string) $actorUserId,
            'value_type' => 'integer',
            'is_secret' => false,
            'is_active' => true,
            'sort_order' => 594,
            'description' => 'Tài khoản service account chạy scheduler automation.',
            'updated_at' => $now,
            'created_at' => $now,
        ]);

        DB::table('clinic_settings')->updateOrInsert([
            'key' => 'scheduler.automation_actor_required_role',
        ], [
            'group' => 'scheduler',
            'label' => 'Automation actor required role',
            'value' => $roleName,
            'value_type' => 'text',
            'is_secret' => false,
            'is_active' => true,
            'sort_order' => 594,
            'description' => 'Role tối thiểu bắt buộc cho scheduler actor.',
            'updated_at' => $now,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', []);
        $columnNames = config('permission.column_names', []);

        $rolesTable = $tableNames['roles'] ?? 'roles';
        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $guardName = (string) config('auth.defaults.guard', 'web');

        $roleId = DB::table($rolesTable)
            ->where('name', 'AutomationService')
            ->where('guard_name', $guardName)
            ->value('id');
        $permissionId = DB::table($permissionsTable)
            ->where('name', 'Action:AutomationRun')
            ->where('guard_name', $guardName)
            ->value('id');
        $actorUserId = Schema::hasTable('users')
            ? DB::table('users')->where('email', 'automation.scheduler@local.invalid')->value('id')
            : null;

        if ($roleId && $permissionId && Schema::hasTable($roleHasPermissionsTable)) {
            DB::table($roleHasPermissionsTable)
                ->where('role_id', (int) $roleId)
                ->where('permission_id', (int) $permissionId)
                ->delete();
        }

        if ($roleId && $actorUserId && Schema::hasTable($modelHasRolesTable)) {
            DB::table($modelHasRolesTable)
                ->where('role_id', (int) $roleId)
                ->where($modelMorphKey, (int) $actorUserId)
                ->where('model_type', \App\Models\User::class)
                ->delete();
        }

        if (Schema::hasTable('clinic_settings')) {
            DB::table('clinic_settings')
                ->whereIn('key', [
                    'scheduler.automation_actor_user_id',
                    'scheduler.automation_actor_required_role',
                ])
                ->delete();
        }
    }
};
