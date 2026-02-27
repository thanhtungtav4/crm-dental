<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', []);

        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';
        $roleHasPermissionsTable = $tableNames['role_has_permissions'] ?? 'role_has_permissions';
        $guardName = (string) config('auth.defaults.guard', 'web');
        $now = now();

        $roleNames = ['Admin', 'Manager', 'Doctor', 'CSKH'];
        $roleMatrix = [
            'Action:PaymentReversal' => ['Admin', 'Manager'],
            'Action:AppointmentOverride' => ['Admin', 'Manager', 'Doctor'],
            'Action:PlanApproval' => ['Admin', 'Manager', 'Doctor'],
            'Action:AutomationRun' => ['Admin', 'Manager', 'CSKH'],
            'Action:MasterDataSync' => ['Admin', 'Manager'],
            'Action:InsuranceClaimDecision' => ['Admin', 'Manager'],
            'Action:MpiDedupeReview' => ['Admin', 'Manager'],
            'Action:PatientBranchTransfer' => ['Admin', 'Manager', 'CSKH'],
        ];
        $permissionNames = array_keys($roleMatrix);

        foreach ($roleNames as $roleName) {
            $exists = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', $guardName)
                ->exists();

            if (! $exists) {
                DB::table($rolesTable)->insert([
                    'name' => $roleName,
                    'guard_name' => $guardName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($permissionNames as $permissionName) {
            $exists = DB::table($permissionsTable)
                ->where('name', $permissionName)
                ->where('guard_name', $guardName)
                ->exists();

            if (! $exists) {
                DB::table($permissionsTable)->insert([
                    'name' => $permissionName,
                    'guard_name' => $guardName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $rolesByName = DB::table($rolesTable)
            ->where('guard_name', $guardName)
            ->whereIn('name', $roleNames)
            ->pluck('id', 'name');

        $permissionsByName = DB::table($permissionsTable)
            ->where('guard_name', $guardName)
            ->whereIn('name', $permissionNames)
            ->pluck('id', 'name');

        foreach ($roleMatrix as $permissionName => $allowedRoles) {
            $permissionId = (int) ($permissionsByName[$permissionName] ?? 0);
            if ($permissionId <= 0) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $roleId = (int) ($rolesByName[$roleName] ?? 0);
                if ($roleId <= 0) {
                    continue;
                }

                $isAllowed = in_array($roleName, $allowedRoles, true);

                if ($isAllowed) {
                    DB::table($roleHasPermissionsTable)->updateOrInsert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ], [
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                } else {
                    DB::table($roleHasPermissionsTable)
                        ->where('permission_id', $permissionId)
                        ->where('role_id', $roleId)
                        ->delete();
                }
            }
        }
    }

    public function down(): void
    {
        //
    }
};
