<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\FinancialAccess;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithFinancialBranchScope
{
    public static function canView(): bool
    {
        return FinancialAccess::canViewDashboard();
    }

    protected function scopedInvoiceQuery(): Builder
    {
        return $this->scopeFinancialQueryToAccessibleBranches(Invoice::query());
    }

    protected function scopedPaymentQuery(): Builder
    {
        return $this->scopeFinancialQueryToAccessibleBranches(Payment::query());
    }

    protected function scopeFinancialQueryToAccessibleBranches(Builder $query, string $column = 'branch_id'): Builder
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser, true);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }
}
