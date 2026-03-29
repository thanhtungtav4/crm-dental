<?php

namespace App\Filament\Widgets;

use App\Services\OperationalStatsReadModelService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = app(OperationalStatsReadModelService::class)->summary(auth()->user());

        return [
            Stat::make('Khách hàng mới (Hôm nay)', $summary['new_customers_today'])
                ->description('Lead & Khách vãng lai')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('Lịch hẹn hôm nay', $summary['appointments_today'])
                ->description('Tổng số ca đặt lịch')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Chờ xác nhận', $summary['pending_confirmations'])
                ->description('Lịch hẹn chưa xác nhận')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
