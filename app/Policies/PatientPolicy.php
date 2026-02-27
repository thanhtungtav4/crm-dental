<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Patient') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Patient $patient): bool
    {
        return $authUser->can('View:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Patient') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Patient $patient): bool
    {
        return $authUser->can('Update:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function delete(User $authUser, Patient $patient): bool
    {
        return $authUser->can('Delete:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function restore(User $authUser, Patient $patient): bool
    {
        return $authUser->can('Restore:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function forceDelete(User $authUser, Patient $patient): bool
    {
        return $authUser->can('ForceDelete:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Patient') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Patient') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Patient $patient): bool
    {
        return $authUser->can('Replicate:Patient') && $this->canAccessPatient($authUser, $patient);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Patient') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessPatient(User $authUser, Patient $patient): bool
    {
        $branchId = $patient->first_branch_id ?? $patient->customer?->branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
