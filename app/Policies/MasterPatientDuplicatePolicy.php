<?php

namespace App\Policies;

use App\Models\MasterPatientDuplicate;
use App\Models\User;
use App\Support\ActionPermission;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasterPatientDuplicatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(ActionPermission::MPI_DEDUPE_REVIEW) && $user->hasAnyAccessibleBranch();
    }

    public function view(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return $user->can(ActionPermission::MPI_DEDUPE_REVIEW)
            && $masterPatientDuplicate->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return $user->can(ActionPermission::MPI_DEDUPE_REVIEW)
            && $masterPatientDuplicate->isReviewableBy($user);
    }

    public function delete(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return false;
    }

    public function restore(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return false;
    }

    public function forceDelete(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, MasterPatientDuplicate $masterPatientDuplicate): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
