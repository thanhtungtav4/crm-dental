<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Payment') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Payment $payment): bool
    {
        return $authUser->can('View:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Payment') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Payment $payment): bool
    {
        return $authUser->can('Update:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function delete(User $authUser, Payment $payment): bool
    {
        return $authUser->can('Delete:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function restore(User $authUser, Payment $payment): bool
    {
        return $authUser->can('Restore:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function forceDelete(User $authUser, Payment $payment): bool
    {
        return $authUser->can('ForceDelete:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Payment') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Payment') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Payment $payment): bool
    {
        return $authUser->can('Replicate:Payment') && $this->canAccessPayment($authUser, $payment);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Payment') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessPayment(User $authUser, Payment $payment): bool
    {
        return $authUser->canAccessBranch($payment->resolveBranchId());
    }
}
