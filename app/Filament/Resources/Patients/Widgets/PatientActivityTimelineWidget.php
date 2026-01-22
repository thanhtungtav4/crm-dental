<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Patient;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class PatientActivityTimelineWidget extends Widget
{
    protected string $view = 'filament.resources.patients.widgets.patient-activity-timeline-widget';

    public ?Patient $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getActivities(): Collection
    {
        if (!$this->record) {
            return collect();
        }

        $activities = collect();

        // Appointments
        $this->record->appointments()->with('doctor', 'branch')->get()->each(function ($appointment) use ($activities) {
            $activities->push([
                'date' => $appointment->date,
                'type' => 'appointment',
                'icon' => 'heroicon-o-calendar',
                'color' => match($appointment->status) {
                    'completed' => 'success',
                    'cancelled', 'no_show' => 'danger',
                    'in_progress' => 'info',
                    default => 'gray',
                },
                'title' => 'Lịch hẹn',
                'description' => $appointment->title ?? 'Lịch hẹn',
                'meta' => [
                    'Bác sĩ' => $appointment->doctor?->name ?? 'N/A',
                    'Trạng thái' => __(ucfirst(str_replace('_', ' ', $appointment->status))),
                    'Chi nhánh' => $appointment->branch?->name ?? 'N/A',
                ],
                'url' => route('filament.admin.resources.appointments.edit', ['record' => $appointment->id]),
            ]);
        });

        // Treatment Plans
        $this->record->treatmentPlans()->with('doctor')->get()->each(function ($plan) use ($activities) {
            $activities->push([
                'date' => $plan->created_at,
                'type' => 'treatment_plan',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => match($plan->status) {
                    'completed' => 'success',
                    'in_progress' => 'info',
                    'approved' => 'warning',
                    'cancelled' => 'danger',
                    default => 'gray',
                },
                'title' => 'Kế hoạch điều trị',
                'description' => $plan->name,
                'meta' => [
                    'Mã KH' => $plan->plan_code,
                    'Bác sĩ' => $plan->doctor?->name ?? 'N/A',
                    'Trạng thái' => __(ucfirst($plan->status)),
                    'Tổng chi phí' => number_format($plan->total_cost, 0, ',', '.') . 'đ',
                ],
                'url' => route('filament.admin.resources.treatment-plans.edit', ['record' => $plan->id]),
            ]);
        });

        // Invoices
        $this->record->invoices()->with('treatmentPlan')->get()->each(function ($invoice) use ($activities) {
            $activities->push([
                'date' => $invoice->invoice_date,
                'type' => 'invoice',
                'icon' => 'heroicon-o-document-text',
                'color' => match($invoice->status) {
                    'paid' => 'success',
                    'overdue' => 'danger',
                    'partial' => 'warning',
                    'cancelled' => 'gray',
                    default => 'info',
                },
                'title' => 'Hóa đơn',
                'description' => 'Hóa đơn #' . $invoice->invoice_number,
                'meta' => [
                    'Kế hoạch' => $invoice->treatmentPlan?->name ?? 'Không có',
                    'Tổng tiền' => number_format($invoice->total_amount, 0, ',', '.') . 'đ',
                    'Đã thanh toán' => number_format($invoice->paid_amount, 0, ',', '.') . 'đ',
                    'Trạng thái' => __(ucfirst($invoice->status)),
                ],
                'url' => route('filament.admin.resources.invoices.edit', ['record' => $invoice->id]),
            ]);
        });

        // Payments
        $this->record->invoices()->with('payments.paymentMethod')->get()->flatMap->payments->each(function ($payment) use ($activities) {
            $activities->push([
                'date' => $payment->payment_date,
                'type' => 'payment',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
                'title' => 'Thanh toán',
                'description' => 'Thanh toán cho hóa đơn #' . $payment->invoice->invoice_number,
                'meta' => [
                    'Số tiền' => number_format($payment->amount, 0, ',', '.') . 'đ',
                    'Phương thức' => $payment->paymentMethod?->name ?? 'N/A',
                    'Mã giao dịch' => $payment->transaction_id ?? 'N/A',
                ],
                'url' => route('filament.admin.resources.payments.edit', ['record' => $payment->id]),
            ]);
        });

        // Notes
        $this->record->notes()->with('user')->get()->each(function ($note) use ($activities) {
            $activities->push([
                'date' => $note->created_at,
                'type' => 'note',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'gray',
                'title' => 'Ghi chú',
                'description' => $note->note,
                'meta' => [
                    'Người tạo' => $note->user?->name ?? 'N/A',
                    'Loại' => __(ucfirst(str_replace('_', ' ', $note->note_type ?? 'general'))),
                ],
                'url' => null,
            ]);
        });

        return $activities->sortByDesc('date')->take(20);
    }
}
