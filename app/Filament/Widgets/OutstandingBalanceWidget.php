<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Models\Invoice;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OutstandingBalanceWidget extends StatsOverviewWidget
{
    use InteractsWithFinancialBranchScope;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $unpaidCount = $this->scopedInvoiceQuery()
            ->unpaid()
            ->count();
        $unpaidTotal = $this->scopedInvoiceQuery()
            ->unpaid()
            ->sum('total_amount');

        $partialCount = $this->scopedInvoiceQuery()
            ->partiallyPaid()
            ->count();
        $partialBalance = $this->scopedInvoiceQuery()
            ->partiallyPaid()
            ->get()
            ->sum(fn (Invoice $invoice): float => $invoice->calculateBalance());

        $overdueCount = $this->scopedInvoiceQuery()
            ->overdue()
            ->count();
        $overdueBalance = $this->scopedInvoiceQuery()
            ->overdue()
            ->get()
            ->sum(fn (Invoice $invoice): float => $invoice->calculateBalance());

        $weekCollections = $this->scopedPaymentQuery()
            ->thisWeek()
            ->sum('amount');
        $weekPaymentsCount = $this->scopedPaymentQuery()
            ->thisWeek()
            ->count();

        return [
            Stat::make('Hóa đơn chưa thanh toán', $unpaidCount.' hóa đơn')
                ->description('Tổng: '.number_format($unpaidTotal, 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('warning')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['payment_progress' => ['value' => 'unpaid']],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn chưa có khoản thanh toán nào',
                ]),

            Stat::make('Thanh toán một phần', $partialCount.' hóa đơn')
                ->description('Còn lại: '.number_format($partialBalance, 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['partial']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã thanh toán một phần',
                ]),

            Stat::make('Hóa đơn quá hạn', $overdueCount.' hóa đơn')
                ->description('Nợ: '.number_format($overdueBalance, 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã quá ngày đến hạn',
                ]),

            Stat::make('Thu tuần này', number_format($weekCollections, 0, ',', '.').'đ')
                ->description($weekPaymentsCount.' giao dịch')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(route('filament.admin.resources.payments.index'))
                ->extraAttributes([
                    'title' => 'Tổng thu từ đầu tuần đến nay',
                ]),
        ];
    }
}
