<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Support\Collection;

class PatientActivityTimelineReadModelService
{
    public function __construct(
        protected PatientOperationalTimelineService $patientOperationalTimelineService,
        protected ClinicalAuditTimelineService $clinicalAuditTimelineService,
    ) {}

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    public function timelineEntriesForPatient(Patient|int $patient, int $limit = 20): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $patientRecord = $this->resolvePatient($patient);

        if (! $patientRecord instanceof Patient) {
            return collect();
        }

        return $this->appointmentEntries($patientRecord, $limit)
            ->concat($this->treatmentPlanEntries($patientRecord, $limit))
            ->concat($this->invoiceEntries($patientRecord, $limit))
            ->concat($this->paymentEntries($patientRecord, $limit))
            ->concat($this->patientOperationalTimelineService->timelineEntriesForPatient($patientRecord, $limit))
            ->concat($this->clinicalAuditTimelineService->timelineEntriesForPatient($patientRecord, min($limit, 10)))
            ->concat($this->noteEntries($patientRecord, $limit))
            ->concat($this->branchLogEntries($patientRecord, $limit))
            ->sortByDesc('date')
            ->take($limit)
            ->values();
    }

    protected function resolvePatient(Patient|int $patient): ?Patient
    {
        if ($patient instanceof Patient) {
            return $patient;
        }

        $patientId = (int) $patient;

        if ($patientId <= 0) {
            return null;
        }

        return Patient::query()->find($patientId);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function appointmentEntries(Patient $patient, int $limit): Collection
    {
        return $patient->appointments()
            ->with(['doctor', 'branch'])
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $appointment): array => [
                'date' => $appointment->date,
                'type' => 'appointment',
                'icon' => 'heroicon-o-calendar',
                'color' => Appointment::statusColor($appointment->status),
                'title' => 'Lịch hẹn',
                'description' => $appointment->chief_complaint ?: ($appointment->note ?: 'Lịch hẹn'),
                'meta' => [
                    'Bác sĩ' => $appointment->doctor?->name ?? 'N/A',
                    'Trạng thái' => Appointment::statusLabel($appointment->status),
                    'Chi nhánh' => $appointment->branch?->name ?? 'N/A',
                ],
                'url' => route('filament.admin.resources.appointments.edit', ['record' => $appointment->id]),
            ]);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function treatmentPlanEntries(Patient $patient, int $limit): Collection
    {
        return $patient->treatmentPlans()
            ->with('doctor')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($plan): array => [
                'date' => $plan->created_at,
                'type' => 'treatment_plan',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => match ($plan->status) {
                    'completed' => 'success',
                    'in_progress' => 'info',
                    'approved' => 'warning',
                    'cancelled' => 'danger',
                    default => 'gray',
                },
                'title' => 'Kế hoạch điều trị',
                'description' => (string) $plan->title,
                'meta' => [
                    'Mã KH' => 'KH-'.$plan->id,
                    'Bác sĩ' => $plan->doctor?->name ?? 'N/A',
                    'Trạng thái' => __(ucfirst((string) $plan->status)),
                    'Tổng chi phí' => number_format((float) $plan->total_cost, 0, ',', '.').'đ',
                ],
                'url' => route('filament.admin.resources.treatment-plans.edit', ['record' => $plan->id]),
            ]);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function invoiceEntries(Patient $patient, int $limit): Collection
    {
        return $patient->invoices()
            ->with('plan')
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($invoice): array => [
                'date' => $invoice->issued_at ?? $invoice->created_at,
                'type' => 'invoice',
                'icon' => 'heroicon-o-document-text',
                'color' => match ($invoice->status) {
                    'paid' => 'success',
                    'partial' => 'warning',
                    'cancelled' => 'gray',
                    default => 'info',
                },
                'title' => 'Hóa đơn',
                'description' => 'Hóa đơn #'.$invoice->invoice_no,
                'meta' => [
                    'Kế hoạch' => $invoice->plan?->title ?? 'Không có',
                    'Tổng tiền' => number_format((float) $invoice->total_amount, 0, ',', '.').'đ',
                    'Đã thanh toán' => number_format((float) $invoice->paid_amount, 0, ',', '.').'đ',
                    'Trạng thái' => __(ucfirst((string) $invoice->status)),
                ],
                'url' => route('filament.admin.resources.invoices.edit', ['record' => $invoice->id]),
            ]);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function paymentEntries(Patient $patient, int $limit): Collection
    {
        return $patient->payments()
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment): array => [
                'date' => $payment->paid_at ?? $payment->created_at,
                'type' => 'payment',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
                'title' => 'Thanh toán',
                'description' => 'Thanh toán cho hóa đơn #'.($payment->invoice?->invoice_no ?? '-'),
                'meta' => [
                    'Số tiền' => number_format((float) $payment->amount, 0, ',', '.').'đ',
                    'Phương thức' => $payment->getMethodLabel(),
                    'Mã giao dịch' => $payment->transaction_ref ?? 'N/A',
                ],
                'url' => route('filament.admin.resources.payments.view', ['record' => $payment->id]),
            ]);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function noteEntries(Patient $patient, int $limit): Collection
    {
        return $patient->notes()
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($note): array => [
                'date' => $note->created_at,
                'type' => 'note',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'gray',
                'title' => 'Ghi chú',
                'description' => (string) $note->content,
                'meta' => [
                    'Người tạo' => $note->user?->name ?? 'N/A',
                    'Loại' => __(ucfirst(str_replace('_', ' ', (string) ($note->type ?? 'general')))),
                ],
                'url' => null,
            ]);
    }

    /**
     * @return Collection<int, array{date:mixed,type:string,icon:string,color:string,title:string,description:string,meta:array<string, mixed>,url:?string}>
     */
    protected function branchLogEntries(Patient $patient, int $limit): Collection
    {
        return $patient->branchLogs()
            ->with(['fromBranch', 'toBranch', 'mover'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($log): array => [
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
    }
}
