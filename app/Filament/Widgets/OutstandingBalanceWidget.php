<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithFinancialBranchScope;
use App\Services\FinancialDashboardReadModelService;
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
        $balances = app(FinancialDashboardReadModelService::class)
            ->outstandingBalances(auth()->user());

        return [
            Stat::make('Hóa đơn chưa thanh toán', $balances['unpaid_count'].' hóa đơn')
                ->description('Tổng: '.number_format($balances['unpaid_total'], 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('warning')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['payment_progress' => ['value' => 'unpaid']],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn chưa có khoản thanh toán nào',
                ]),

            Stat::make('Thanh toán một phần', $balances['partial_count'].' hóa đơn')
                ->description('Còn lại: '.number_format($balances['partial_balance'], 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['partial']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã thanh toán một phần',
                ]),

            Stat::make('Hóa đơn quá hạn', $balances['overdue_count'].' hóa đơn')
                ->description('Nợ: '.number_format($balances['overdue_balance'], 0, ',', '.').'đ')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã quá ngày đến hạn',
                ]),

            Stat::make('Thu tuần này', number_format($balances['week_collections'], 0, ',', '.').'đ')
                ->description($balances['week_payments_count'].' giao dịch')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(route('filament.admin.resources.payments.index'))
                ->extraAttributes([
                    'title' => 'Tổng thu từ đầu tuần đến nay',
                ]),
        ];
    }
}
