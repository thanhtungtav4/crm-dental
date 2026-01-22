<?php

namespace App\Filament\Widgets;

use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuickFinancialStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 6;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        // Total revenue all time
        $totalRevenue = Payment::sum('amount');
        $totalPayments = Payment::count();
        $avgPayment = $totalPayments > 0 ? $totalRevenue / $totalPayments : 0;
        
        // Total invoices
        $totalInvoices = Invoice::count();
        $paidInvoices = Invoice::fullyPaid()->count();
        $paidPercentage = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;
        
        // Cash vs Non-cash
        $cashPayments = Payment::cash()->sum('amount');
        $cardPayments = Payment::card()->sum('amount');
        $transferPayments = Payment::transfer()->sum('amount');
        $nonCashTotal = $cardPayments + $transferPayments;
        $cashPercentage = $totalRevenue > 0 ? round(($cashPayments / $totalRevenue) * 100, 1) : 0;
        
        // Installment plans stats
        $totalPlans = InstallmentPlan::count();
        $activePlans = InstallmentPlan::active()->count();
        $completedPlans = InstallmentPlan::completed()->count();
        $completionRate = $totalPlans > 0 ? round(($completedPlans / $totalPlans) * 100, 1) : 0;
        
        // Average invoice value
        $avgInvoice = $totalInvoices > 0 ? Invoice::avg('total_amount') : 0;
        $highestInvoice = Invoice::max('total_amount') ?? 0;
        
        // Payment frequency
        $thisMonthPayments = Payment::thisMonth()->count();
        $lastMonthPayments = Payment::query()
            ->whereYear('paid_at', now()->subMonth()->year)
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->count();
        $frequencyChange = $lastMonthPayments > 0 
            ? round((($thisMonthPayments - $lastMonthPayments) / $lastMonthPayments) * 100, 1)
            : 0;
        
        return [
            Stat::make('Tổng doanh thu', number_format($totalRevenue, 0, ',', '.') . 'đ')
                ->description($totalPayments . ' giao dịch | TB: ' . number_format($avgPayment, 0, ',', '.') . 'đ')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->color('success')
                ->extraAttributes([
                    'title' => 'Tổng doanh thu từ tất cả các khoản thanh toán',
                ]),
            
            Stat::make('Tỷ lệ thanh toán', $paidPercentage . '%')
                ->description("{$paidInvoices}/{$totalInvoices} hóa đơn đã thanh toán đầy đủ")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($paidPercentage >= 70 ? 'success' : ($paidPercentage >= 50 ? 'warning' : 'danger'))
                ->chart([$paidPercentage, 100 - $paidPercentage])
                ->extraAttributes([
                    'title' => 'Phần trăm hóa đơn đã thanh toán hoàn tất',
                ]),
            
            Stat::make('Tiền mặt / Phi tiền mặt', number_format($cashPayments, 0, ',', '.') . 'đ')
                ->description('Phi tiền mặt: ' . number_format($nonCashTotal, 0, ',', '.') . 'đ (' . (100 - $cashPercentage) . '%)')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('info')
                ->extraAttributes([
                    'title' => 'So sánh thanh toán tiền mặt và phi tiền mặt',
                ]),
            
            Stat::make('Kế hoạch trả góp', $totalPlans . ' kế hoạch')
                ->description("{$completedPlans} hoàn thành ({$completionRate}%) | {$activePlans} đang hoạt động")
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color($activePlans > 0 ? 'success' : 'gray')
                ->url(route('filament.admin.resources.installment-plans.index'))
                ->extraAttributes([
                    'title' => 'Tổng số kế hoạch trả góp và trạng thái',
                ]),
            
            Stat::make('Giá trị HĐ trung bình', number_format($avgInvoice, 0, ',', '.') . 'đ')
                ->description('Cao nhất: ' . number_format($highestInvoice, 0, ',', '.') . 'đ')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->extraAttributes([
                    'title' => 'Giá trị trung bình của các hóa đơn',
                ]),
            
            Stat::make('Tần suất thanh toán', $thisMonthPayments . ' thanh toán/tháng')
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
