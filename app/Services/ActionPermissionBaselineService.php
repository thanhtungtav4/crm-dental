<?php

namespace App\Services;

use App\Support\ActionPermission;
use App\Support\SensitiveActionRegistry;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ActionPermissionBaselineService
{
    public function sync(): array
    {
        $guard = $this->guardName();
        $roles = $this->syncRolesForMatrix($guard);
        $permissions = $this->syncPermissions($guard);

        $granted = 0;
        $revoked = 0;
        $matrix = SensitiveActionRegistry::roleMatrix();

        foreach ($matrix as $permissionName => $allowedRoles) {
            $permission = $permissions->get($permissionName);

            if (! $permission) {
                continue;
            }

            foreach ($roles as $roleName => $role) {
                $shouldAllow = in_array($roleName, $allowedRoles, true);
                $hasPermission = $role->hasPermissionTo($permissionName);

                if ($shouldAllow && ! $hasPermission) {
                    $role->givePermissionTo($permission);
                    $granted++;
                }

                if (! $shouldAllow && $hasPermission) {
                    $role->revokePermissionTo($permission);
                    $revoked++;
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'created_roles' => count($roles),
            'upserted_permissions' => count($permissions),
            'granted' => $granted,
            'revoked' => $revoked,
        ];
    }

    public function report(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = $this->guardName();
        $matrix = SensitiveActionRegistry::roleMatrix();
        $roles = $this->ensureRoleCollection($guard);
        $permissionNames = ActionPermission::all();

        $existingPermissions = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->all();

        $missingPermissions = array_values(array_diff($permissionNames, $existingPermissions));
        $existingPermissionLookup = array_fill_keys($existingPermissions, true);
        $missingRoles = array_values(array_diff($this->roleNames(), $roles->keys()->all()));
        $matrixMismatches = [];

        foreach ($matrix as $permissionName => $allowedRoles) {
            if (! array_key_exists($permissionName, $existingPermissionLookup)) {
                continue;
            }

            foreach ($this->roleNames() as $roleName) {
                $role = $roles->get($roleName);
                $expected = in_array($roleName, $allowedRoles, true);
                $actual = $role?->hasPermissionTo($permissionName) ?? false;

                if ($actual !== $expected) {
                    $matrixMismatches[] = [
                        'permission' => $permissionName,
                        'role' => $roleName,
                        'expected' => $expected,
                        'actual' => $actual,
                    ];
                }
            }
        }

        return [
            'guard' => $guard,
            'missing_permissions' => $missingPermissions,
            'missing_roles' => $missingRoles,
            'matrix_mismatches' => $matrixMismatches,
            'ok' => $missingPermissions === [] && $missingRoles === [] && $matrixMismatches === [],
        ];
    }

    protected function guardName(): string
    {
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * @return Collection<string, Role>
     */
    protected function syncRolesForMatrix(string $guard): Collection
    {
        return collect($this->roleNames())
            ->mapWithKeys(function (string $roleName) use ($guard): array {
                $role = Role::query()->firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => $guard,
                ]);

                return [$roleName => $role];
            });
    }

    /**
     * @return Collection<string, Permission>
     */
    protected function syncPermissions(string $guard): Collection
    {
        return collect(ActionPermission::all())
            ->mapWithKeys(function (string $permissionName) use ($guard): array {
                $permission = Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);

                return [$permissionName => $permission];
            });
    }

    /**
     * @return Collection<string, Role>
     */
    protected function ensureRoleCollection(string $guard): Collection
    {
        return Role::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $this->roleNames())
            ->get()
            ->keyBy('name');
    }

    /**
     * @return array<int, string>
     */
    protected function roleNames(): array
    {
        return collect(SensitiveActionRegistry::roleMatrix())
            ->flatMap(fn (array $roles): array => $roles)
            ->unique()
            ->values()
            ->all();
    }
}
