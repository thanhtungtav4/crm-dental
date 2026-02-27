<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Note') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Note $note): bool
    {
        return $authUser->can('View:Note') && $this->canAccessNote($authUser, $note);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Note') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Note $note): bool
    {
        return $authUser->can('Update:Note') && $this->canAccessNote($authUser, $note);
    }

    public function delete(User $authUser, Note $note): bool
    {
        return $authUser->can('Delete:Note') && $this->canAccessNote($authUser, $note);
    }

    public function restore(User $authUser, Note $note): bool
    {
        return $authUser->can('Restore:Note') && $this->canAccessNote($authUser, $note);
    }

    public function forceDelete(User $authUser, Note $note): bool
    {
        return $authUser->can('ForceDelete:Note') && $this->canAccessNote($authUser, $note);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Note') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Note') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Note $note): bool
    {
        return $authUser->can('Replicate:Note') && $this->canAccessNote($authUser, $note);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Note') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessNote(User $authUser, Note $note): bool
    {
        $branchId = $note->patient?->first_branch_id ?? $note->customer?->branch_id;

        return $authUser->canAccessBranch($branchId ? (int) $branchId : null);
    }
}
