<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Patient;
use App\Services\PatientOverviewReadModelService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatientOverviewWidget extends BaseWidget
{
    public ?Patient $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $overview = app(PatientOverviewReadModelService::class)->overview($this->record);

        return [
            Stat::make('Kế hoạch điều trị', $overview['treatment_plans_count'])
                ->description($overview['active_treatment_plans_count'] > 0 ? "{$overview['active_treatment_plans_count']} đang thực hiện" : 'Không có KH đang thực hiện')
                ->descriptionIcon($overview['active_treatment_plans_count'] > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($overview['active_treatment_plans_count'] > 0 ? 'success' : 'gray'),

            Stat::make('Hóa đơn', $overview['invoices_count'])
                ->description($overview['unpaid_invoices_count'] > 0
                    ? 'Còn nợ: '.number_format($overview['total_owed'], 0, ',', '.').'đ'
                    : 'Tất cả đã thanh toán')
                ->descriptionIcon($overview['unpaid_invoices_count'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overview['unpaid_invoices_count'] > 0 ? 'warning' : 'success'),

            Stat::make('Lịch hẹn', $overview['appointments_count'])
                ->description($overview['upcoming_appointments_count'] > 0
                    ? "{$overview['upcoming_appointments_count']} lịch sắp tới"
                    : 'Không có lịch sắp tới')
                ->descriptionIcon($overview['upcoming_appointments_count'] > 0 ? 'heroicon-m-calendar' : 'heroicon-m-calendar-days')
                ->color($overview['upcoming_appointments_count'] > 0 ? 'info' : 'gray'),

            Stat::make('Lịch hẹn tiếp theo', $overview['next_appointment_at'] ? $overview['next_appointment_at']->format('d/m/Y H:i') : 'Chưa có')
                ->description($overview['next_appointment_at'] ? $overview['next_appointment_at']->diffForHumans() : 'Chưa đặt lịch')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($overview['next_appointment_at'] ? 'primary' : 'gray'),
        ];
    }
}
