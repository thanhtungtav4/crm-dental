<?php

namespace App\Policies;

use App\Models\MaterialIssueNote;
use App\Models\User;

class MaterialIssueNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']) && $user->hasAnyAccessibleBranch();
    }

    public function view(User $user, MaterialIssueNote $materialIssueNote): bool
    {
        return $this->viewAny($user) && $user->canAccessBranch((int) $materialIssueNote->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MaterialIssueNote $materialIssueNote): bool
    {
        return $this->view($user, $materialIssueNote)
            && $materialIssueNote->status === MaterialIssueNote::STATUS_DRAFT;
    }

    public function delete(User $user, MaterialIssueNote $materialIssueNote): bool
    {
        return false;
    }

    public function restore(User $user, MaterialIssueNote $materialIssueNote): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaterialIssueNote $materialIssueNote): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
