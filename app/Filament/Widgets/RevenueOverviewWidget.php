<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $overview = app(FinancialDashboardReadModelService::class)
            ->revenueOverview(auth()->user());

        $todayChange = $overview['today_change'];
        $todayChangeIcon = $todayChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown;
        $todayChangeColor = $todayChange >= 0 ? 'success' : 'danger';
        $monthChange = $overview['month_change'];
        $monthChangeIcon = $monthChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown;
        $monthChangeColor = $monthChange >= 0 ? 'success' : 'danger';

        return [
            Stat::make('Doanh thu hôm nay', number_format($overview['today_revenue'], 0, ',', '.').'đ')
                ->description($todayChange != 0 ? abs($todayChange).'% so với hôm qua' : 'Không có thay đổi')
                ->descriptionIcon($todayChangeIcon)
                ->color($todayChangeColor)
                ->chart($overview['last_7_days'])
                ->extraAttributes([
                    'title' => 'Tổng doanh thu từ các khoản thanh toán hôm nay',
                ]),

            Stat::make('Doanh thu tháng này', number_format($overview['this_month_revenue'], 0, ',', '.').'đ')
                ->description($monthChange != 0 ? abs($monthChange).'% so với tháng trước' : 'Không có thay đổi')
                ->descriptionIcon($monthChangeIcon)
                ->color($monthChangeColor)
                ->extraAttributes([
                    'title' => 'Tổng doanh thu tháng '.now()->format('m/Y'),
                ]),

            Stat::make('Tổng công nợ', number_format($overview['total_outstanding'], 0, ',', '.').'đ')
                ->description($overview['overdue_count'] > 0 ? "{$overview['overdue_count']} hóa đơn quá hạn" : 'Không có quá hạn')
                ->descriptionIcon($overview['overdue_count'] > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($overview['overdue_count'] > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue', 'partial']]],
                ]))
                ->extraAttributes([
                    'title' => 'Tổng số tiền chưa thu được từ các hóa đơn',
                ]),
        ];
    }
}
