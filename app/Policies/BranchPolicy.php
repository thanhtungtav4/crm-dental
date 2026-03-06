<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BranchPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole('Admin')
            || ($authUser->can('ViewAny:Branch') && $authUser->hasAnyAccessibleBranch());
    }

    public function view(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('View:Branch') && $branch->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Branch');
    }

    public function update(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Update:Branch') && $branch->isVisibleTo($authUser);
    }

    public function delete(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Delete:Branch') && $branch->isVisibleTo($authUser);
    }

    public function restore(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Restore:Branch') && $branch->isVisibleTo($authUser);
    }

    public function forceDelete(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('ForceDelete:Branch') && $branch->isVisibleTo($authUser);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Branch');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Branch');
    }

    public function replicate(User $authUser, Branch $branch): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Replicate:Branch') && $branch->isVisibleTo($authUser);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Branch');
    }
}
