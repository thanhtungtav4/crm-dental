<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickFinancialStatsWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cards = app(FinancialDashboardReadModelService::class)
            ->quickFinancialStatCards(auth()->user());
        $totalRevenue = $cards['total_revenue'];
        $paymentRate = $cards['payment_rate'];
        $cashMix = $cards['cash_mix'];
        $invoiceAverage = $cards['invoice_average'];
        $paymentFrequency = $cards['payment_frequency'];

        return [
            Stat::make($totalRevenue['label'], $totalRevenue['value'])
                ->description($totalRevenue['description'])
                ->descriptionIcon($totalRevenue['description_icon'])
                ->color($totalRevenue['color'])
                ->extraAttributes([
                    'title' => $totalRevenue['title'],
                ]),

            Stat::make($paymentRate['label'], $paymentRate['value'])
                ->description($paymentRate['description'])
                ->descriptionIcon($paymentRate['description_icon'])
                ->color($paymentRate['color'])
                ->chart($paymentRate['chart'])
                ->extraAttributes([
                    'title' => $paymentRate['title'],
                ]),

            Stat::make($cashMix['label'], $cashMix['value'])
                ->description($cashMix['description'])
                ->descriptionIcon($cashMix['description_icon'])
                ->color($cashMix['color'])
                ->extraAttributes([
                    'title' => $cashMix['title'],
                ]),

            Stat::make($invoiceAverage['label'], $invoiceAverage['value'])
                ->description($invoiceAverage['description'])
                ->descriptionIcon($invoiceAverage['description_icon'])
                ->color($invoiceAverage['color'])
                ->extraAttributes([
                    'title' => $invoiceAverage['title'],
                ]),

            Stat::make($paymentFrequency['label'], $paymentFrequency['value'])
                ->description($paymentFrequency['description'])
                ->descriptionIcon($paymentFrequency['description_icon'])
                ->color($paymentFrequency['color'])
                ->extraAttributes([
                    'title' => $paymentFrequency['title'],
                ]),
        ];
    }
}
