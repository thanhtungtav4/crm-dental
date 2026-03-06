<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\TreatmentDeletionGuardService;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreatmentPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:TreatmentPlan') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('View:TreatmentPlan') && $this->canAccessTreatmentPlan($authUser, $treatmentPlan);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:TreatmentPlan') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Update:TreatmentPlan') && $this->canAccessTreatmentPlan($authUser, $treatmentPlan);
    }

    public function delete(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Delete:TreatmentPlan')
            && $this->canAccessTreatmentPlan($authUser, $treatmentPlan)
            && app(TreatmentDeletionGuardService::class)->canDeleteTreatmentPlan($treatmentPlan);
    }

    public function deleteAny(User $authUser): bool
    {
        return false;
    }

    public function restore(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, TreatmentPlan $treatmentPlan): bool
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

    public function replicate(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        return $authUser->can('Replicate:TreatmentPlan') && $this->canAccessTreatmentPlan($authUser, $treatmentPlan);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:TreatmentPlan') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessTreatmentPlan(User $authUser, TreatmentPlan $treatmentPlan): bool
    {
        $branchId = $treatmentPlan->branch_id ?? $treatmentPlan->patient?->first_branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
