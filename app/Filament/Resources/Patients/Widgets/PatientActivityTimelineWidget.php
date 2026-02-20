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
                'description' => $appointment->chief_complaint ?: ($appointment->note ?: 'Lịch hẹn'),
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
                'description' => $plan->title,
                'meta' => [
                    'Mã KH' => 'KH-' . $plan->id,
                    'Bác sĩ' => $plan->doctor?->name ?? 'N/A',
                    'Trạng thái' => __(ucfirst($plan->status)),
                    'Tổng chi phí' => number_format($plan->total_cost, 0, ',', '.') . 'đ',
                ],
                'url' => route('filament.admin.resources.treatment-plans.edit', ['record' => $plan->id]),
            ]);
        });

        // Invoices
        $this->record->invoices()->with('plan')->get()->each(function ($invoice) use ($activities) {
            $activities->push([
                'date' => $invoice->issued_at ?? $invoice->created_at,
                'type' => 'invoice',
                'icon' => 'heroicon-o-document-text',
                'color' => match($invoice->status) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    'cancelled' => 'gray',
                    default => 'info',
                },
                'title' => 'Hóa đơn',
                'description' => 'Hóa đơn #' . $invoice->invoice_no,
                'meta' => [
                    'Kế hoạch' => $invoice->plan?->title ?? 'Không có',
                    'Tổng tiền' => number_format($invoice->total_amount, 0, ',', '.') . 'đ',
                    'Đã thanh toán' => number_format($invoice->paid_amount, 0, ',', '.') . 'đ',
                    'Trạng thái' => __(ucfirst($invoice->status)),
                ],
                'url' => route('filament.admin.resources.invoices.edit', ['record' => $invoice->id]),
            ]);
        });

        // Payments
        $this->record->invoices()->with('payments')->get()->flatMap->payments->each(function ($payment) use ($activities) {
            $activities->push([
                'date' => $payment->paid_at ?? $payment->created_at,
                'type' => 'payment',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
                'title' => 'Thanh toán',
                'description' => 'Thanh toán cho hóa đơn #' . ($payment->invoice?->invoice_no ?? '-'),
                'meta' => [
                    'Số tiền' => number_format($payment->amount, 0, ',', '.') . 'đ',
                    'Phương thức' => $payment->getMethodLabel(),
                    'Mã giao dịch' => $payment->transaction_ref ?? 'N/A',
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
                'description' => $note->content,
                'meta' => [
                    'Người tạo' => $note->user?->name ?? 'N/A',
                    'Loại' => __(ucfirst(str_replace('_', ' ', $note->type ?? 'general'))),
                ],
                'url' => null,
            ]);
        });

        // Branch logs
        $this->record->branchLogs()->with(['fromBranch', 'toBranch', 'mover'])->get()->each(function ($log) use ($activities) {
            $activities->push([
                'date' => $log->created_at,
                'type' => 'branch_log',
                'icon' => 'heroicon-o-building-office',
                'color' => 'info',
                'title' => 'Chuyển chi nhánh',
                'description' => $log->note ?: 'Cập nhật chi nhánh bệnh nhân',
                'meta' => [
                    'Từ' => $log->fromBranch?->name ?? '-',
                    'Đến' => $log->toBranch?->name ?? '-',
                    'Người thực hiện' => $log->mover?->name ?? 'N/A',
                ],
                'url' => null,
            ]);
        });

        return $activities->sortByDesc('date')->take(20);
    }
}
