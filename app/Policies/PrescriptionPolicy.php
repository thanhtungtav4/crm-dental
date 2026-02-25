<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prescription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PrescriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Prescription');
    }

    public function view(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('View:Prescription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Prescription');
    }

    public function update(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Update:Prescription');
    }

    public function delete(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Delete:Prescription');
    }

    public function restore(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Restore:Prescription');
    }

    public function forceDelete(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('ForceDelete:Prescription');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Prescription');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Prescription');
    }

    public function replicate(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('Replicate:Prescription');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Prescription');
    }
}
