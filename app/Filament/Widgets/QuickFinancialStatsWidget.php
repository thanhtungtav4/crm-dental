<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickFinancialStatsWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = app(FinancialDashboardReadModelService::class)
            ->quickStats(auth()->user());

        return [
            Stat::make('Tổng doanh thu', number_format($stats['total_revenue'], 0, ',', '.').'đ')
                ->description($stats['total_payments'].' giao dịch | TB: '.number_format($stats['avg_payment'], 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('success')
                ->extraAttributes([
                    'title' => 'Tổng doanh thu từ tất cả các khoản thanh toán',
                ]),

            Stat::make('Tỷ lệ thanh toán', $stats['paid_percentage'].'%')
                ->description("{$stats['paid_invoices']}/{$stats['total_invoices']} hóa đơn đã thanh toán đầy đủ")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($stats['paid_percentage'] >= 70 ? 'success' : ($stats['paid_percentage'] >= 50 ? 'warning' : 'danger'))
                ->chart([$stats['paid_percentage'], 100 - $stats['paid_percentage']])
                ->extraAttributes([
                    'title' => 'Phần trăm hóa đơn đã thanh toán hoàn tất',
                ]),

            Stat::make('Tiền mặt / Phi tiền mặt', number_format($stats['cash_payments'], 0, ',', '.').'đ')
                ->description('Phi tiền mặt: '.number_format($stats['non_cash_total'], 0, ',', '.').'đ ('.(100 - $stats['cash_percentage']).'%)')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('info')
                ->extraAttributes([
                    'title' => 'So sánh thanh toán tiền mặt và phi tiền mặt',
                ]),

            Stat::make('Giá trị HĐ trung bình', number_format($stats['avg_invoice'], 0, ',', '.').'đ')
                ->description('Cao nhất: '.number_format($stats['highest_invoice'], 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->extraAttributes([
                    'title' => 'Giá trị trung bình của các hóa đơn',
                ]),

            Stat::make('Tần suất thanh toán', $stats['this_month_payments'].' thanh toán/tháng')
                ->description(
                    $stats['frequency_change'] > 0
                        ? "+{$stats['frequency_change']}% so với tháng trước"
                        : ($stats['frequency_change'] < 0 ? "{$stats['frequency_change']}% so với tháng trước" : 'Không đổi')
                )
                ->descriptionIcon($stats['frequency_change'] >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->color($stats['frequency_change'] >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'title' => 'Số lượng thanh toán trung bình mỗi tháng',
                ]),
        ];
    }
}
