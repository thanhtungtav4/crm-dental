<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MaterialBatch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialBatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:MaterialBatch') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('View:MaterialBatch') && $this->canAccessMaterialBatch($authUser, $materialBatch);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:MaterialBatch') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Update:MaterialBatch') && $this->canAccessMaterialBatch($authUser, $materialBatch);
    }

    public function delete(User $authUser, MaterialBatch $materialBatch): bool
    {
        return false;
    }

    public function restore(User $authUser, MaterialBatch $materialBatch): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, MaterialBatch $materialBatch): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, MaterialBatch $materialBatch): bool
    {
        return $authUser->can('Replicate:MaterialBatch') && $this->canAccessMaterialBatch($authUser, $materialBatch);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:MaterialBatch') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessMaterialBatch(User $authUser, MaterialBatch $materialBatch): bool
    {
        $branchId = $materialBatch->material?->branch_id;

        if ($branchId === null && $materialBatch->material_id !== null) {
            $branchId = $materialBatch->material()
                ->withoutGlobalScopes()
                ->value('branch_id');
        }

        return $authUser->canAccessBranch($branchId !== null ? (int) $branchId : null);
    }
}
