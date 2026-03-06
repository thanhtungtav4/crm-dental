<?php

namespace App\Policies;

use App\Models\PatientWallet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientWalletPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:PatientWallet') && $user->hasAnyAccessibleBranch();
    }

    public function view(User $user, PatientWallet $patientWallet): bool
    {
        return $user->can('View:PatientWallet') && $this->canAccessWallet($user, $patientWallet);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PatientWallet $patientWallet): bool
    {
        return $user->can('Update:PatientWallet') && $this->canAccessWallet($user, $patientWallet);
    }

    public function delete(User $user, PatientWallet $patientWallet): bool
    {
        return false;
    }

    public function restore(User $user, PatientWallet $patientWallet): bool
    {
        return false;
    }

    public function forceDelete(User $user, PatientWallet $patientWallet): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, PatientWallet $patientWallet): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function canAccessWallet(User $user, PatientWallet $patientWallet): bool
    {
        return $user->canAccessBranch($patientWallet->resolveBranchId());
    }
}
