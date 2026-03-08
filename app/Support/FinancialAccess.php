<?php

namespace App\Support;

use App\Models\User;

class FinancialAccess
{
    public static function canViewDashboard(?User $user = null): bool
    {
        $authUser = $user ?? BranchAccess::currentUser();

        return $authUser instanceof User
            && $authUser->hasAnyAccessibleBranch()
            && $authUser->can('ViewAny:Invoice')
            && $authUser->can('ViewAny:Payment')
            && $authUser->can('View:FinancialDashboard');
    }
}
