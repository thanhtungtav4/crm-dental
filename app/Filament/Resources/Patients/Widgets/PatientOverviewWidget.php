<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Appointment;
use App\Models\Patient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PatientOverviewWidget extends BaseWidget
{
    public ?Patient $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $treatmentPlansCount = $this->record->treatmentPlans()->count();
        $activeTPCount = $this->record->treatmentPlans()->whereIn('status', ['approved', 'in_progress'])->count();

        $invoicesCount = $this->record->invoices()->count();
        $unpaidInvoices = $this->record->invoices()->whereIn('status', ['issued', 'partial', 'overdue'])->count();
        $totalOwed = $this->record->invoices()
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->get()
            ->sum(fn($invoice) => $invoice->total_amount - $invoice->paid_amount);

        $appointmentsCount = $this->record->appointments()->count();
        $upcomingAppointments = $this->record->appointments()
            ->where('date', '>', now())
            ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
            ->count();

        $totalSpent = $this->record->invoices()->where('status', 'paid')->sum('total_amount');
        $totalPaid = $this->record->invoices()->sum('paid_amount');

        $lastVisit = $this->record->appointments()
            ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]))
            ->latest('date')
            ->first();

        $nextAppointment = $this->record->appointments()
            ->where('date', '>', now())
            ->whereIn('status', Appointment::statusesForQuery(Appointment::activeStatuses()))
            ->oldest('date')
            ->first();

        return [
            Stat::make('Kế hoạch điều trị', $treatmentPlansCount)
                ->description($activeTPCount > 0 ? "{$activeTPCount} đang thực hiện" : 'Không có KH đang thực hiện')
                ->descriptionIcon($activeTPCount > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($activeTPCount > 0 ? 'success' : 'gray'),

            Stat::make('Hóa đơn', $invoicesCount)
                ->description($unpaidInvoices > 0
                    ? "Còn nợ: " . number_format($totalOwed, 0, ',', '.') . 'đ'
                    : 'Tất cả đã thanh toán')
                ->descriptionIcon($unpaidInvoices > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($unpaidInvoices > 0 ? 'warning' : 'success'),

            Stat::make('Lịch hẹn', $appointmentsCount)
                ->description($upcomingAppointments > 0
                    ? "{$upcomingAppointments} lịch sắp tới"
                    : 'Không có lịch sắp tới')
                ->descriptionIcon($upcomingAppointments > 0 ? 'heroicon-m-calendar' : 'heroicon-m-calendar-days')
                ->color($upcomingAppointments > 0 ? 'info' : 'gray'),

            Stat::make('Lịch hẹn tiếp theo', $nextAppointment ? $nextAppointment->date->format('d/m/Y H:i') : 'Chưa có')
                ->description($nextAppointment ? $nextAppointment->date->diffForHumans() : 'Chưa đặt lịch')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($nextAppointment ? 'primary' : 'gray'),
        ];
    }
}
