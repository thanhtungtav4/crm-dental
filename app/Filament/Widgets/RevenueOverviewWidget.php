<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $cards = app(FinancialDashboardReadModelService::class)
            ->revenueOverviewCards(auth()->user());
        $today = $cards['today'];
        $month = $cards['month'];
        $outstanding = $cards['outstanding'];

        return [
            Stat::make($today['label'], $today['value'])
                ->description($today['description'])
                ->descriptionIcon($today['description_icon'])
                ->color($today['color'])
                ->chart($today['chart'])
                ->extraAttributes([
                    'title' => $today['title'],
                ]),

            Stat::make($month['label'], $month['value'])
                ->description($month['description'])
                ->descriptionIcon($month['description_icon'])
                ->color($month['color'])
                ->extraAttributes([
                    'title' => $month['title'],
                ]),

            Stat::make($outstanding['label'], $outstanding['value'])
                ->description($outstanding['description'])
                ->descriptionIcon($outstanding['description_icon'])
                ->color($outstanding['color'])
                ->url($outstanding['url'])
                ->extraAttributes([
                    'title' => $outstanding['title'],
                ]),
        ];
    }
}
