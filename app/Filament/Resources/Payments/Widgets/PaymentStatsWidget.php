<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected function getStats(): array
    {
        $cards = app(FinancialDashboardReadModelService::class)
            ->paymentStatsCards(auth()->user());
        $today = $cards['today'];
        $methods = $cards['methods'];
        $unpaid = $cards['unpaid'];

        return [
            Stat::make($today['label'], $today['value'])
                ->description($today['description'])
                ->descriptionIcon($today['description_icon'])
                ->color($today['color'])
                ->chart($today['chart']),

            Stat::make($methods['label'], $methods['value'])
                ->description($methods['description'])
                ->descriptionIcon($methods['description_icon'])
                ->color($methods['color'])
                ->extraAttributes([
                    'class' => 'cursor-help',
                    'title' => $methods['title'],
                ]),

            Stat::make($unpaid['label'], $unpaid['value'])
                ->description($unpaid['description'])
                ->descriptionIcon($unpaid['description_icon'])
                ->color($unpaid['color'])
                ->url($unpaid['url']),
        ];
    }
}
