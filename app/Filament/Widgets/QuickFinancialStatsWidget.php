<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
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
        $totalRevenue = $this->scopedPaymentQuery()->sum('amount');
        $totalPayments = $this->scopedPaymentQuery()->count();
        $avgPayment = $totalPayments > 0 ? $totalRevenue / $totalPayments : 0;

        $totalInvoices = $this->scopedInvoiceQuery()->count();
        $paidInvoices = $this->scopedInvoiceQuery()->fullyPaid()->count();
        $paidPercentage = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;

        $cashPayments = $this->scopedPaymentQuery()->cash()->sum('amount');
        $cardPayments = $this->scopedPaymentQuery()->card()->sum('amount');
        $transferPayments = $this->scopedPaymentQuery()->transfer()->sum('amount');
        $nonCashTotal = $cardPayments + $transferPayments;
        $cashPercentage = $totalRevenue > 0 ? round(($cashPayments / $totalRevenue) * 100, 1) : 0;

        $avgInvoice = $totalInvoices > 0 ? $this->scopedInvoiceQuery()->avg('total_amount') : 0;
        $highestInvoice = $this->scopedInvoiceQuery()->max('total_amount') ?? 0;

        $thisMonthPayments = $this->scopedPaymentQuery()
            ->thisMonth()
            ->count();
        $lastMonthPayments = $this->scopedPaymentQuery()
            ->whereYear('paid_at', now()->subMonth()->year)
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->count();
        $frequencyChange = $lastMonthPayments > 0
            ? round((($thisMonthPayments - $lastMonthPayments) / $lastMonthPayments) * 100, 1)
            : 0;

        return [
            Stat::make('Tổng doanh thu', number_format($totalRevenue, 0, ',', '.').'đ')
                ->description($totalPayments.' giao dịch | TB: '.number_format($avgPayment, 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('success')
                ->extraAttributes([
                    'title' => 'Tổng doanh thu từ tất cả các khoản thanh toán',
                ]),

            Stat::make('Tỷ lệ thanh toán', $paidPercentage.'%')
                ->description("{$paidInvoices}/{$totalInvoices} hóa đơn đã thanh toán đầy đủ")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($paidPercentage >= 70 ? 'success' : ($paidPercentage >= 50 ? 'warning' : 'danger'))
                ->chart([$paidPercentage, 100 - $paidPercentage])
                ->extraAttributes([
                    'title' => 'Phần trăm hóa đơn đã thanh toán hoàn tất',
                ]),

            Stat::make('Tiền mặt / Phi tiền mặt', number_format($cashPayments, 0, ',', '.').'đ')
                ->description('Phi tiền mặt: '.number_format($nonCashTotal, 0, ',', '.').'đ ('.(100 - $cashPercentage).'%)')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('info')
                ->extraAttributes([
                    'title' => 'So sánh thanh toán tiền mặt và phi tiền mặt',
                ]),

            Stat::make('Giá trị HĐ trung bình', number_format($avgInvoice, 0, ',', '.').'đ')
                ->description('Cao nhất: '.number_format($highestInvoice, 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->extraAttributes([
                    'title' => 'Giá trị trung bình của các hóa đơn',
                ]),

            Stat::make('Tần suất thanh toán', $thisMonthPayments.' thanh toán/tháng')
                ->description(
                    $frequencyChange > 0
                        ? "+{$frequencyChange}% so với tháng trước"
                        : ($frequencyChange < 0 ? "{$frequencyChange}% so với tháng trước" : 'Không đổi')
                )
                ->descriptionIcon($frequencyChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown)
                ->color($frequencyChange >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'title' => 'Số lượng thanh toán trung bình mỗi tháng',
                ]),
        ];
    }
}
