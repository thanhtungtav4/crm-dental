<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:PlanItem') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('View:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:PlanItem') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Update:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function delete(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Delete:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function restore(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Restore:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function forceDelete(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('ForceDelete:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanItem') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanItem') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->can('Replicate:PlanItem') && $this->canAccessPlanItem($authUser, $planItem);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:PlanItem') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessPlanItem(User $authUser, PlanItem $planItem): bool
    {
        return $authUser->canAccessBranch($planItem->resolveBranchId());
    }
}
