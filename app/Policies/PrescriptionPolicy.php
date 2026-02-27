<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrescriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Prescription') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('View:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Prescription') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Update:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function delete(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Delete:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function restore(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Restore:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function forceDelete(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('ForceDelete:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Prescription') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Prescription') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Replicate:Prescription') && $this->canAccessPrescription($authUser, $prescription);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Prescription') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessPrescription(User $authUser, Prescription $prescription): bool
    {
        $branchId = $prescription->patient?->first_branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
