<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreatmentMaterial;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentMaterialPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentMaterial') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->can('View:TreatmentMaterial') && $this->canAccessTreatmentMaterial($authUser, $treatmentMaterial);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:TreatmentMaterial') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return false;
    }

    public function delete(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return false;
    }

    public function restore(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, TreatmentMaterial $treatmentMaterial): bool
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

    public function replicate(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentMaterial') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessTreatmentMaterial(User $authUser, TreatmentMaterial $treatmentMaterial): bool
    {
        return $authUser->canAccessBranch($treatmentMaterial->resolveBranchId());
    }
}
