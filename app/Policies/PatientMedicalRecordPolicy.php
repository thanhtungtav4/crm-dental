<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientMedicalRecord;
use App\Models\User;
use App\Support\ActionPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientMedicalRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return (
            $authUser->can('ViewAny:PatientMedicalRecord')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return (
            $authUser->can('View:PatientMedicalRecord')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function create(User $authUser): bool
    {
        return (
            $authUser->can('Create:PatientMedicalRecord')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return (
            $authUser->can('Update:PatientMedicalRecord')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function delete(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Delete:PatientMedicalRecord') && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function restore(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Restore:PatientMedicalRecord') && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function forceDelete(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('ForceDelete:PatientMedicalRecord') && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PatientMedicalRecord') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:PatientMedicalRecord') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        return $authUser->can('Replicate:PatientMedicalRecord') && $this->canAccessRecord($authUser, $patientMedicalRecord);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:PatientMedicalRecord') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessRecord(User $authUser, PatientMedicalRecord $patientMedicalRecord): bool
    {
        $branchId = $patientMedicalRecord->patient?->first_branch_id
            ?? $patientMedicalRecord->patient?->customer?->branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
