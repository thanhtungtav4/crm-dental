<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\FinancialAccess;

trait InteractsWithFinancialBranchScope
{
    public static function canView(): bool
    {
        return FinancialAccess::canViewDashboard();
    }
}
