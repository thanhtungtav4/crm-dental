<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = $this->scopedPaymentQuery()
            ->today()
            ->sum('amount');
        $yesterdayRevenue = $this->scopedPaymentQuery()
            ->whereDate('paid_at', Carbon::yesterday())
            ->sum('amount');
        $todayChange = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : 0;
        $todayChangeIcon = $todayChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown;
        $todayChangeColor = $todayChange >= 0 ? 'success' : 'danger';

        $thisMonthRevenue = $this->scopedPaymentQuery()
            ->thisMonth()
            ->sum('amount');
        $lastMonthRevenue = $this->scopedPaymentQuery()
            ->whereYear('paid_at', now()->subMonth()->year)
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->sum('amount');
        $monthChange = $lastMonthRevenue > 0
            ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : 0;
        $monthChangeIcon = $monthChange >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown;
        $monthChangeColor = $monthChange >= 0 ? 'success' : 'danger';

        $totalOutstanding = $this->scopedInvoiceQuery()
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->get()
            ->sum(fn (Invoice $invoice): float => $invoice->calculateBalance());
        $overdueCount = $this->scopedInvoiceQuery()
            ->overdue()
            ->count();

        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            return $this->scopedPaymentQuery()
                ->whereDate('paid_at', Carbon::today()->subDays($daysAgo))
                ->sum('amount');
        })->toArray();

        return [
            Stat::make('Doanh thu hôm nay', number_format($todayRevenue, 0, ',', '.').'đ')
                ->description($todayChange != 0 ? abs($todayChange).'% so với hôm qua' : 'Không có thay đổi')
                ->descriptionIcon($todayChangeIcon)
                ->color($todayChangeColor)
                ->chart($last7Days)
                ->extraAttributes([
                    'title' => 'Tổng doanh thu từ các khoản thanh toán hôm nay',
                ]),

            Stat::make('Doanh thu tháng này', number_format($thisMonthRevenue, 0, ',', '.').'đ')
                ->description($monthChange != 0 ? abs($monthChange).'% so với tháng trước' : 'Không có thay đổi')
                ->descriptionIcon($monthChangeIcon)
                ->color($monthChangeColor)
                ->extraAttributes([
                    'title' => 'Tổng doanh thu tháng '.now()->format('m/Y'),
                ]),

            Stat::make('Tổng công nợ', number_format($totalOutstanding, 0, ',', '.').'đ')
                ->description($overdueCount > 0 ? "{$overdueCount} hóa đơn quá hạn" : 'Không có quá hạn')
                ->descriptionIcon($overdueCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($overdueCount > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue', 'partial']]],
                ]))
                ->extraAttributes([
                    'title' => 'Tổng số tiền chưa thu được từ các hóa đơn',
                ]),
        ];
    }
}
