<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserProvisioningAuthorizer
{
    public function canManageRoles(?User $actor): bool
    {
        return $actor instanceof User && $actor->hasRole('Admin');
    }

    public function canManageDirectPermissions(?User $actor): bool
    {
        return $this->canManageRoles($actor);
    }

    public function scopeAssignableBranches(Builder $query, ?User $actor, bool $activeOnly = true): Builder
    {
        if ($activeOnly) {
            $query->where('active', true);
        }

        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->hasRole('Admin')) {
            return $query->orderBy('name');
        }

        $branchIds = collect($actor->accessibleBranchIds())
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->filter(fn (int $branchId): bool => $branchId > 0)
            ->unique()
            ->values()
            ->all();

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('id', $branchIds)
            ->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public function assignableBranchOptions(?User $actor): array
    {
        return $this->scopeAssignableBranches(Branch::query(), $actor)
            ->pluck('name', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public function scopeAssignableRoles(Builder $query, ?User $actor): Builder
    {
        if (! $this->canManageRoles($actor)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->orderBy('name');
    }

    public function scopeAssignablePermissions(Builder $query, ?User $actor): Builder
    {
        if (! $this->canManageDirectPermissions($actor)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    public function assignablePermissionOptions(?User $actor): array
    {
        if (! $this->canManageDirectPermissions($actor)) {
            return [];
        }

        $options = [];
        $permissions = $this->scopeAssignablePermissions(Permission::query(), $actor)->get();

        foreach ($permissions as $permission) {
            [, $resource] = array_pad(explode(':', $permission->name, 2), 2, 'other');
            $normalizedResource = Str::kebab((string) $resource);
            $group = match ($normalizedResource) {
                'user' => 'Người dùng',
                'branch' => 'Chi nhánh',
                'customer' => 'Khách hàng',
                'patient' => 'Bệnh nhân',
                'treatment-plan' => 'Kế hoạch điều trị',
                'treatment-session' => 'Phiên điều trị',
                'plan-item' => 'Hạng mục điều trị',
                'material' => 'Vật tư',
                'treatment-material' => 'Vật tư sử dụng',
                'invoice' => 'Hóa đơn',
                'payment' => 'Thanh toán',
                'note' => 'Ghi chú',
                'appointment' => 'Lịch hẹn',
                default => 'Khác',
            };

            $label = str_replace([':', '_', '-'], ' ', $permission->name);
            $options[$permission->id] = $group.' — '.ucwords($label);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeFormData(?User $actor, array $data): array
    {
        $data['branch_id'] = $this->sanitizeBranchId(
            actor: $actor,
            branchId: isset($data['branch_id']) && filled($data['branch_id']) ? (int) $data['branch_id'] : null,
        );

        $data['doctor_branch_ids'] = $this->sanitizeBranchIds(
            actor: $actor,
            branchIds: (array) ($data['doctor_branch_ids'] ?? []),
            field: 'doctor_branch_ids',
        );

        $data['roles'] = $this->sanitizeRoleIds($actor, (array) ($data['roles'] ?? []));
        $data['permissions'] = $this->sanitizePermissionIds($actor, (array) ($data['permissions'] ?? []));

        return $data;
    }

    protected function sanitizeBranchId(?User $actor, ?int $branchId): ?int
    {
        if ($branchId === null) {
            return null;
        }

        $allowedBranchIds = array_keys($this->assignableBranchOptions($actor));

        if (! in_array($branchId, $allowedBranchIds, true)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Bạn không được phép gán người dùng vào chi nhánh ngoài phạm vi được phép.',
            ]);
        }

        return $branchId;
    }

    /**
     * @param  array<int, mixed>  $branchIds
     * @return array<int, int>
     */
    protected function sanitizeBranchIds(?User $actor, array $branchIds, string $field): array
    {
        $normalizedBranchIds = collect($branchIds)
            ->filter(fn (mixed $branchId): bool => filled($branchId))
            ->map(fn (mixed $branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedBranchIds === []) {
            return [];
        }

        $allowedBranchIds = array_keys($this->assignableBranchOptions($actor));
        $invalidBranchIds = array_values(array_diff($normalizedBranchIds, $allowedBranchIds));

        if ($invalidBranchIds !== []) {
            throw ValidationException::withMessages([
                $field => 'Bạn không được phép gán người dùng vào chi nhánh ngoài phạm vi được phép.',
            ]);
        }

        return $normalizedBranchIds;
    }

    /**
     * @param  array<int, mixed>  $roleIds
     * @return array<int, int>
     */
    protected function sanitizeRoleIds(?User $actor, array $roleIds): array
    {
        $normalizedRoleIds = collect($roleIds)
            ->filter(fn (mixed $roleId): bool => filled($roleId))
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedRoleIds === []) {
            return [];
        }

        if (! $this->canManageRoles($actor)) {
            throw ValidationException::withMessages([
                'roles' => 'Chỉ Admin được phép gán vai trò cho người dùng.',
            ]);
        }

        $allowedRoleIds = $this->scopeAssignableRoles(Role::query(), $actor)
            ->pluck('id')
            ->map(fn (mixed $roleId): int => (int) $roleId)
            ->all();

        $invalidRoleIds = array_values(array_diff($normalizedRoleIds, $allowedRoleIds));

        if ($invalidRoleIds !== []) {
            throw ValidationException::withMessages([
                'roles' => 'Danh sách vai trò được gán không hợp lệ.',
            ]);
        }

        return $normalizedRoleIds;
    }

    /**
     * @param  array<int, mixed>  $permissionIds
     * @return array<int, int>
     */
    protected function sanitizePermissionIds(?User $actor, array $permissionIds): array
    {
        $normalizedPermissionIds = collect($permissionIds)
            ->filter(fn (mixed $permissionId): bool => filled($permissionId))
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->unique()
            ->values()
            ->all();

        if ($normalizedPermissionIds === []) {
            return [];
        }

        if (! $this->canManageDirectPermissions($actor)) {
            throw ValidationException::withMessages([
                'permissions' => 'Chỉ Admin được phép gán quyền trực tiếp cho người dùng.',
            ]);
        }

        $allowedPermissionIds = $this->scopeAssignablePermissions(Permission::query(), $actor)
            ->pluck('id')
            ->map(fn (mixed $permissionId): int => (int) $permissionId)
            ->all();

        $invalidPermissionIds = array_values(array_diff($normalizedPermissionIds, $allowedPermissionIds));

        if ($invalidPermissionIds !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'Danh sách quyền trực tiếp không hợp lệ.',
            ]);
        }

        return $normalizedPermissionIds;
    }
}
