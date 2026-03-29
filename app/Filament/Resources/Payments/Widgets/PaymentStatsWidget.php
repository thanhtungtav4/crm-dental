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
        $service = app(FinancialDashboardReadModelService::class);
        $overview = $service->revenueOverview(auth()->user());
        $quickStats = $service->quickStats(auth()->user());
        $balances = $service->outstandingBalances(auth()->user());
        $todayDiff = $overview['today_change'];
        $methodTotal = $quickStats['cash_payments'] + $quickStats['card_payments'] + $quickStats['transfer_payments'];

        return [
            Stat::make('💰 Tổng thu hôm nay', number_format($overview['today_revenue'], 0, ',', '.').'đ')
                ->description($todayDiff >= 0
                    ? 'Tăng '.abs($todayDiff).'% so với hôm qua'
                    : 'Giảm '.abs($todayDiff).'% so với hôm qua')
                ->descriptionIcon($todayDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayDiff >= 0 ? 'success' : 'danger')
                ->chart($overview['last_7_days']),

            Stat::make('💳 Theo phương thức', number_format($methodTotal, 0, ',', '.').'đ')
                ->description('Tiền mặt: '.number_format($quickStats['cash_payments'], 0, ',', '.').'đ | Thẻ: '.number_format($quickStats['card_payments'], 0, ',', '.').'đ')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->extraAttributes([
                    'class' => 'cursor-help',
                    'title' => 'Chuyển khoản: '.number_format($quickStats['transfer_payments'], 0, ',', '.').'đ | Bảo hiểm: '.number_format($quickStats['insurance_payments'], 0, ',', '.').'đ',
                ]),

            Stat::make('⏰ Hóa đơn chưa thanh toán', $balances['unpaid_count'])
                ->description('Tổng: '.number_format($balances['unpaid_total'], 0, ',', '.').'đ | Quá hạn: '.$balances['overdue_count'])
                ->descriptionIcon($balances['overdue_count'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($balances['overdue_count'] > 0 ? 'danger' : 'warning')
                ->url(route('filament.admin.resources.invoices.index', ['tableFilters' => ['status' => ['value' => ['issued', 'partial']]]])),
        ];
    }
}
