<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Customer') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Customer $customer): bool
    {
        return $authUser->can('View:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Customer') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Customer $customer): bool
    {
        return $authUser->can('Update:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function delete(User $authUser, Customer $customer): bool
    {
        return $authUser->can('Delete:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function restore(User $authUser, Customer $customer): bool
    {
        return $authUser->can('Restore:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function forceDelete(User $authUser, Customer $customer): bool
    {
        return $authUser->can('ForceDelete:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Customer') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Customer') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Customer $customer): bool
    {
        return $authUser->can('Replicate:Customer') && $this->canAccessCustomer($authUser, $customer);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Customer') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessCustomer(User $authUser, Customer $customer): bool
    {
        return $authUser->canAccessBranch($customer->branch_id);
    }
}
