<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OutstandingBalanceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        // Unpaid invoices
        $unpaidCount = Invoice::unpaid()->count();
        $unpaidTotal = Invoice::unpaid()->sum('total_amount');
        
        // Partially paid invoices
        $partialCount = Invoice::partiallyPaid()->count();
        $partialBalance = Invoice::partiallyPaid()
            ->get()
            ->sum(fn($invoice) => $invoice->calculateBalance());
        
        // Overdue invoices
        $overdueCount = Invoice::overdue()->count();
        $overdueBalance = Invoice::overdue()
            ->get()
            ->sum(fn($invoice) => $invoice->calculateBalance());
        
        // This week's collections
        $weekCollections = Payment::thisWeek()->sum('amount');
        $weekPaymentsCount = Payment::thisWeek()->count();
        
        return [
            Stat::make('Hóa đơn chưa thanh toán', $unpaidCount . ' hóa đơn')
                ->description('Tổng: ' . number_format($unpaidTotal, 0, ',', '.') . 'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('warning')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['payment_progress' => ['value' => 'unpaid']],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn chưa có khoản thanh toán nào',
                ]),
            
            Stat::make('Thanh toán một phần', $partialCount . ' hóa đơn')
                ->description('Còn lại: ' . number_format($partialBalance, 0, ',', '.') . 'đ')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['partial']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã thanh toán một phần',
                ]),
            
            Stat::make('Hóa đơn quá hạn', $overdueCount . ' hóa đơn')
                ->description('Nợ: ' . number_format($overdueBalance, 0, ',', '.') . 'đ')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(route('filament.admin.resources.invoices.index', [
                    'tableFilters' => ['status' => ['values' => ['overdue']]],
                ]))
                ->extraAttributes([
                    'title' => 'Hóa đơn đã quá ngày đến hạn',
                ]),
            
            Stat::make('Thu tuần này', number_format($weekCollections, 0, ',', '.') . 'đ')
                ->description($weekPaymentsCount . ' giao dịch')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(route('filament.admin.resources.payments.index'))
                ->extraAttributes([
                    'title' => 'Tổng thu từ đầu tuần đến nay',
                ]),
        ];
    }
}
