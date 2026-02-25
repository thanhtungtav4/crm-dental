<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientPhoto;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PatientPhotoPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PatientPhoto');
    }

    public function view(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('View:PatientPhoto');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PatientPhoto');
    }

    public function update(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('Update:PatientPhoto');
    }

    public function delete(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('Delete:PatientPhoto');
    }

    public function restore(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('Restore:PatientPhoto');
    }

    public function forceDelete(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('ForceDelete:PatientPhoto');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PatientPhoto');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PatientPhoto');
    }

    public function replicate(AuthUser $authUser, PatientPhoto $patientPhoto): bool
    {
        return $authUser->can('Replicate:PatientPhoto');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PatientPhoto');
    }
}
