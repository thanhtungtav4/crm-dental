<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OutstandingBalanceWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cards = app(FinancialDashboardReadModelService::class)
            ->outstandingBalanceCards(auth()->user());
        $unpaid = $cards['unpaid'];
        $partial = $cards['partial'];
        $overdue = $cards['overdue'];
        $week = $cards['week'];

        return [
            Stat::make($unpaid['label'], $unpaid['value'])
                ->description($unpaid['description'])
                ->descriptionIcon($unpaid['description_icon'])
                ->color($unpaid['color'])
                ->url($unpaid['url'])
                ->extraAttributes([
                    'title' => $unpaid['title'],
                ]),

            Stat::make($partial['label'], $partial['value'])
                ->description($partial['description'])
                ->descriptionIcon($partial['description_icon'])
                ->color($partial['color'])
                ->url($partial['url'])
                ->extraAttributes([
                    'title' => $partial['title'],
                ]),

            Stat::make($overdue['label'], $overdue['value'])
                ->description($overdue['description'])
                ->descriptionIcon($overdue['description_icon'])
                ->color($overdue['color'])
                ->url($overdue['url'])
                ->extraAttributes([
                    'title' => $overdue['title'],
                ]),

            Stat::make($week['label'], $week['value'])
                ->description($week['description'])
                ->descriptionIcon($week['description_icon'])
                ->color($week['color'])
                ->url($week['url'])
                ->extraAttributes([
                    'title' => $week['title'],
                ]),
        ];
    }
}
