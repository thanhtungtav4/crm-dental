<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\TreatmentDeletionGuardService;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentSession') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('View:TreatmentSession') && $this->canAccessSession($authUser, $treatmentSession);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:TreatmentSession') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Update:TreatmentSession') && $this->canAccessSession($authUser, $treatmentSession);
    }

    public function delete(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Delete:TreatmentSession')
            && $this->canAccessSession($authUser, $treatmentSession)
            && app(TreatmentDeletionGuardService::class)->canDeleteTreatmentSession($treatmentSession);
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, TreatmentSession $treatmentSession): bool
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

    public function replicate(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->can('Replicate:TreatmentSession') && $this->canAccessSession($authUser, $treatmentSession);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentSession') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessSession(User $authUser, TreatmentSession $treatmentSession): bool
    {
        return $authUser->canAccessBranch($treatmentSession->resolveBranchId());
    }
}
