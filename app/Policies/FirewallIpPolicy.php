<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use SolutionForest\FilamentFirewall\Models\Ip;

class FirewallIpPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function view(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function create(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function update(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function delete(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function restore(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function forceDelete(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function deleteAny(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function restoreAny(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function replicate(User $authUser, Ip $ip): bool
    {
        return $this->canManageFirewall($authUser);
    }

    public function reorder(User $authUser): bool
    {
        return $this->canManageFirewall($authUser);
    }

    protected function canManageFirewall(User $authUser): bool
    {
        return $authUser->hasRole('Admin');
    }
}
