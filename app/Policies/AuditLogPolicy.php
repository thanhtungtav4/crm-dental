<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole('Admin')
            || ($authUser->can('ViewAny:AuditLog') && $authUser->hasAnyAccessibleBranch());
    }

    public function view(User $authUser, AuditLog $auditLog): bool
    {
        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->can('View:AuditLog') && $auditLog->isVisibleTo($authUser);
    }

    public function create(User $authUser): bool
    {
        return false;
    }

    public function update(User $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function restore(User $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, AuditLog $auditLog): bool
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

    public function replicate(User $authUser, AuditLog $auditLog): bool
    {
        return false;
    }

    public function reorder(User $authUser): bool
    {
        return false;
    }
}
