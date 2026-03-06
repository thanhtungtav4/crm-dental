<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BranchLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole('Admin')
            || ($authUser->can('ViewAny:BranchLog') && $authUser->hasAnyAccessibleBranch());
    }

    public function view(User $authUser, BranchLog $branchLog): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('View:BranchLog') && $branchLog->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, BranchLog $branchLog): bool
    {
        return false;
    }

    public function delete(User $authUser, BranchLog $branchLog): bool
    {
        return false;
    }

    public function restore(User $authUser, BranchLog $branchLog): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, BranchLog $branchLog): bool
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

    public function replicate(User $authUser, BranchLog $branchLog): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}
