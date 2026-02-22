<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OperationalStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();

        // 1. New Leads/Customers Created Today
        $newLeadsCount = Customer::where('created_at', '>=', $today)
            ->count();

        // 2. Appointments Today
        $appointmentsTodayCount = Appointment::whereDate('date', $today)
            ->count();

        // 3. Pending Confirmations (appointments đã đặt nhưng chưa xác nhận)
        $pendingConfirmations = Appointment::whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))
            ->where('date', '>=', now())
            ->count();

        return [
            Stat::make('Khách hàng mới (Hôm nay)', $newLeadsCount)
                ->description('Lead & Khách vãng lai')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('Lịch hẹn hôm nay', $appointmentsTodayCount)
                ->description('Tổng số ca đặt lịch')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),

            Stat::make('Chờ xác nhận', $pendingConfirmations)
                ->description('Lịch hẹn chưa xác nhận')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
