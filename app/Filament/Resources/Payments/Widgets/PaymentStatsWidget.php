<?php

namespace App\Filament\Resources\Payments\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PaymentStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Total collected today
        $todayTotal = Payment::today()->sum('amount');
        $yesterdayTotal = Payment::whereDate('paid_at', today()->subDay())->sum('amount');
        $todayDiff = $yesterdayTotal > 0 
            ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1)
            : 0;

        // Total by method
        $cashTotal = Payment::cash()->sum('amount');
        $cardTotal = Payment::card()->sum('amount');
        $transferTotal = Payment::transfer()->sum('amount');
        $insuranceTotal = Payment::insuranceOnly()->sum('amount');

        // Outstanding invoices
        $outstandingCount = Invoice::unpaid()->count();
        $outstandingAmount = Invoice::unpaid()->sum('total_amount');
        $overdueCount = Invoice::overdue()->count();

        return [
            Stat::make('💰 Tổng thu hôm nay', number_format($todayTotal, 0, ',', '.') . 'đ')
                ->description($todayDiff >= 0 
                    ? 'Tăng ' . abs($todayDiff) . '% so với hôm qua' 
                    : 'Giảm ' . abs($todayDiff) . '% so với hôm qua')
                ->descriptionIcon($todayDiff >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayDiff >= 0 ? 'success' : 'danger')
                ->chart([
                    Payment::whereDate('paid_at', today()->subDays(6))->sum('amount'),
                    Payment::whereDate('paid_at', today()->subDays(5))->sum('amount'),
                    Payment::whereDate('paid_at', today()->subDays(4))->sum('amount'),
                    Payment::whereDate('paid_at', today()->subDays(3))->sum('amount'),
                    Payment::whereDate('paid_at', today()->subDays(2))->sum('amount'),
                    Payment::whereDate('paid_at', today()->subDay())->sum('amount'),
                    $todayTotal,
                ]),

            Stat::make('💳 Theo phương thức', number_format($cashTotal + $cardTotal + $transferTotal, 0, ',', '.') . 'đ')
                ->description('Tiền mặt: ' . number_format($cashTotal, 0, ',', '.') . 'đ | Thẻ: ' . number_format($cardTotal, 0, ',', '.') . 'đ')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->extraAttributes([
                    'class' => 'cursor-help',
                    'title' => 'Chuyển khoản: ' . number_format($transferTotal, 0, ',', '.') . 'đ | Bảo hiểm: ' . number_format($insuranceTotal, 0, ',', '.') . 'đ',
                ]),

            Stat::make('⏰ Hóa đơn chưa thanh toán', $outstandingCount)
                ->description('Tổng: ' . number_format($outstandingAmount, 0, ',', '.') . 'đ | Quá hạn: ' . $overdueCount)
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overdueCount > 0 ? 'danger' : 'warning')
                ->url(route('filament.admin.resources.invoices.index', ['tableFilters' => ['status' => ['value' => ['issued', 'partial']]]])),
        ];
    }
}
