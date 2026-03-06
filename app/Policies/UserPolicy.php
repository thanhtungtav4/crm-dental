<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole('Admin')
            || ($authUser->can('ViewAny:User') && $authUser->hasAnyAccessibleBranch());
    }

    public function view(User $authUser, User $managedUser): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('View:User') && $managedUser->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(User $authUser, User $managedUser): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Update:User') && $managedUser->isVisibleTo($authUser);
    }

    public function delete(User $authUser, User $managedUser): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('Delete:User') && $managedUser->isVisibleTo($authUser);
    }

    public function restore(User $authUser): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(User $authUser): bool
    {
        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(User $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}
