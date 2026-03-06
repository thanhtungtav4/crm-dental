<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Material') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Material $material): bool
    {
        return $authUser->can('View:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Material') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Material $material): bool
    {
        return $authUser->can('Update:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function delete(User $authUser, Material $material): bool
    {
        return $authUser->can('Delete:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function restore(User $authUser, Material $material): bool
    {
        return $authUser->can('Restore:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function forceDelete(User $authUser, Material $material): bool
    {
        return $authUser->can('ForceDelete:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Material') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Material') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Material $material): bool
    {
        return $authUser->can('Replicate:Material') && $this->canAccessMaterial($authUser, $material);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Material') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessMaterial(User $authUser, Material $material): bool
    {
        return $authUser->canAccessBranch($material->branch_id !== null ? (int) $material->branch_id : null);
    }
}
