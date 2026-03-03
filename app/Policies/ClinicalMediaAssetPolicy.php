<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClinicalMediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicalMediaAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:ClinicalMediaAsset') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('View:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:ClinicalMediaAsset') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('Update:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function delete(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('Delete:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function restore(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('Restore:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function forceDelete(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('ForceDelete:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClinicalMediaAsset') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:ClinicalMediaAsset') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->can('Replicate:ClinicalMediaAsset') && $this->canAccessAsset($authUser, $clinicalMediaAsset);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:ClinicalMediaAsset') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessAsset(User $authUser, ClinicalMediaAsset $clinicalMediaAsset): bool
    {
        return $authUser->canAccessBranch($clinicalMediaAsset->resolveBranchId());
    }
}
