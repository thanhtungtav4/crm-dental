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
        return false;
    }

    public function restore(User $authUser, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $authUser, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $authUser): bool
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
