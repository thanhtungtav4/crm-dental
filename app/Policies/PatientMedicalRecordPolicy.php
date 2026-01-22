<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PatientMedicalRecord;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientMedicalRecordPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PatientMedicalRecord');
    }

    public function view(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('View:PatientMedicalRecord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PatientMedicalRecord');
    }

    public function update(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Update:PatientMedicalRecord');
    }

    public function delete(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Delete:PatientMedicalRecord');
    }

    public function restore(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Restore:PatientMedicalRecord');
    }

    public function forceDelete(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('ForceDelete:PatientMedicalRecord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PatientMedicalRecord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PatientMedicalRecord');
    }

    public function replicate(AuthUser $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Replicate:PatientMedicalRecord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PatientMedicalRecord');
    }

}