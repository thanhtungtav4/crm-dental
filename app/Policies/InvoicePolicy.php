<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Invoice') && $authUser->hasAnyAccessibleBranch();
    }

    public function view(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('View:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Invoice') && $authUser->hasAnyAccessibleBranch();
    }

    public function update(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('Update:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function delete(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('Delete:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function restore(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('Restore:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function forceDelete(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('ForceDelete:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Invoice') && $authUser->hasAnyAccessibleBranch();
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Invoice') && $authUser->hasAnyAccessibleBranch();
    }

    public function replicate(User $authUser, Invoice $invoice): bool
    {
        return $authUser->can('Replicate:Invoice') && $this->canAccessInvoice($authUser, $invoice);
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Invoice') && $authUser->hasAnyAccessibleBranch();
    }

    protected function canAccessInvoice(User $authUser, Invoice $invoice): bool
    {
        return $authUser->canAccessBranch($invoice->resolveBranchId());
    }
}
