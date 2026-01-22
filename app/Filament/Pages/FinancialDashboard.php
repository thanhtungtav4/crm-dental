<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Payments\Widgets\PaymentMethodsChartWidget;
use App\Filament\Widgets\MonthlyRevenueChartWidget;
use App\Filament\Widgets\OutstandingBalanceWidget;
use App\Filament\Widgets\OverdueInvoicesWidget;
use App\Filament\Widgets\QuickFinancialStatsWidget;
use App\Filament\Widgets\RevenueOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class FinancialDashboard extends BaseDashboard
{
    protected static string $routePath = 'financial-dashboard';
    
    protected static ?int $navigationSort = 0;
    
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }
    
    public static function getNavigationLabel(): string
    {
        return 'Dashboard Tài chính';
    }
    
    public static function getNavigationGroup(): ?string
    {
        return '2️⃣ Tài chính';
    }
    
    public function getTitle(): string
    {
        return 'Dashboard Tài chính';
    }
    
    public function getWidgets(): array
    {
        return [
            RevenueOverviewWidget::class,
            OutstandingBalanceWidget::class,
            MonthlyRevenueChartWidget::class,
            PaymentMethodsChartWidget::class,
            OverdueInvoicesWidget::class,
            QuickFinancialStatsWidget::class,
        ];
    }
    
    public function getColumns(): int | array
    {
        return 2;
    }
}
