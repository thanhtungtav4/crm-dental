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
        $stats = app(FinancialDashboardReadModelService::class)
            ->paymentStatsSnapshot(auth()->user());
        $todayDiff = $stats['today_change'];

        return [
            Stat::make('💰 Tổng thu hôm nay', number_format($stats['today_revenue'], 0, ',', '.').'đ')
                ->description($todayDiff >= 0
                    ? 'Tăng '.abs($todayDiff).'% so với hôm qua'
                    : 'Giảm '.abs($todayDiff).'% so với hôm qua')
                ->descriptionIcon($todayDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayDiff >= 0 ? 'success' : 'danger')
                ->chart($stats['last_7_days']),

            Stat::make('💳 Theo phương thức', number_format($stats['method_total'], 0, ',', '.').'đ')
                ->description('Tiền mặt: '.number_format($stats['cash_payments'], 0, ',', '.').'đ | Thẻ: '.number_format($stats['card_payments'], 0, ',', '.').'đ')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->extraAttributes([
                    'class' => 'cursor-help',
                    'title' => 'Chuyển khoản: '.number_format($stats['transfer_payments'], 0, ',', '.').'đ | Bảo hiểm: '.number_format($stats['insurance_payments'], 0, ',', '.').'đ',
                ]),

            Stat::make('⏰ Hóa đơn chưa thanh toán', $stats['unpaid_count'])
                ->description('Tổng: '.number_format($stats['unpaid_total'], 0, ',', '.').'đ | Quá hạn: '.$stats['overdue_count'])
                ->descriptionIcon($stats['overdue_count'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($stats['overdue_count'] > 0 ? 'danger' : 'warning')
                ->url(route('filament.admin.resources.invoices.index', ['tableFilters' => ['status' => ['value' => ['issued', 'partial']]]])),
        ];
    }
}
