<?php

namespace App\Policies;

use App\Models\ClinicalNote;
use App\Models\User;
use App\Support\ActionPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClinicalNotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return (
            $authUser->can('ViewAny:ClinicalNote')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return (
            $authUser->can('View:ClinicalNote')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $this->canAccessRecord($authUser, $clinicalNote);
    }

    public function create(User $authUser): bool
    {
        return (
            $authUser->can('Create:ClinicalNote')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return (
            $authUser->can('Update:ClinicalNote')
            || $authUser->can(ActionPermission::EMR_CLINICAL_WRITE)
        ) && $this->canAccessRecord($authUser, $clinicalNote);
    }

    public function delete(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return false;
    }

    public function restoreAny(User $authUser): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return false;
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return false;
    }

    public function replicate(User $authUser, ClinicalNote $clinicalNote): bool
    {
        return $authUser->can('Replicate:ClinicalNote') && $this->canAccessRecord($authUser, $clinicalNote);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:ClinicalNote') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessRecord(User $authUser, ClinicalNote $clinicalNote): bool
    {
        $branchId = $clinicalNote->branch_id
            ?? $clinicalNote->visitEpisode?->branch_id
            ?? $clinicalNote->examSession?->branch_id
            ?? $clinicalNote->patient?->first_branch_id
            ?? $clinicalNote->patient?->customer?->branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
