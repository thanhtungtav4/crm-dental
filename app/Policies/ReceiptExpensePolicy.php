<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReceiptExpense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptExpensePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:ReceiptExpense') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('View:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:ReceiptExpense') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('Update:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function delete(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('Delete:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function restore(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('Restore:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function forceDelete(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('ForceDelete:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReceiptExpense') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:ReceiptExpense') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->can('Replicate:ReceiptExpense') && $this->canAccessReceiptExpense($authUser, $receiptExpense);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:ReceiptExpense') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessReceiptExpense(User $authUser, ReceiptExpense $receiptExpense): bool
    {
        return $authUser->canAccessBranch($receiptExpense->resolveBranchId());
    }
}
