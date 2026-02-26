<?php

namespace App\Filament\Resources\Patients\Widgets;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Note;
use App\Models\Patient;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class PatientActivityTimelineWidget extends Widget
{
    protected string $view = 'filament.resources.patients.widgets.patient-activity-timeline-widget';

    public ?Patient $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getActivities(): Collection
    {
        if (! $this->record) {
            return collect();
        }

        $activities = collect();

        // Appointments
        $this->record->appointments()->with('doctor', 'branch')->get()->each(function ($appointment) use ($activities) {
            $activities->push([
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
        });

        // Treatment Plans
        $this->record->treatmentPlans()->with('doctor')->get()->each(function ($plan) use ($activities) {
            $activities->push([
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
                'description' => $plan->title,
                'meta' => [
                    'Mã KH' => 'KH-'.$plan->id,
                    'Bác sĩ' => $plan->doctor?->name ?? 'N/A',
                    'Trạng thái' => __(ucfirst($plan->status)),
                    'Tổng chi phí' => number_format($plan->total_cost, 0, ',', '.').'đ',
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
                    'Tổng tiền' => number_format($invoice->total_amount, 0, ',', '.').'đ',
                    'Đã thanh toán' => number_format($invoice->paid_amount, 0, ',', '.').'đ',
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
                'description' => 'Thanh toán cho hóa đơn #'.($payment->invoice?->invoice_no ?? '-'),
                'meta' => [
                    'Số tiền' => number_format($payment->amount, 0, ',', '.').'đ',
                    'Phương thức' => $payment->getMethodLabel(),
                    'Mã giao dịch' => $payment->transaction_ref ?? 'N/A',
                ],
                'url' => route('filament.admin.resources.payments.view', ['record' => $payment->id]),
            ]);
        });

        // Audit logs (financial)
        AuditLog::query()
            ->whereIn('entity_type', [AuditLog::ENTITY_PAYMENT, AuditLog::ENTITY_INVOICE])
            ->whereIn('action', [
                AuditLog::ACTION_REFUND,
                AuditLog::ACTION_REVERSAL,
                AuditLog::ACTION_CANCEL,
                AuditLog::ACTION_UPDATE,
            ])
            ->where('metadata->patient_id', $this->record->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->each(function (AuditLog $log) use ($activities) {
                $label = match ($log->action) {
                    AuditLog::ACTION_REFUND => 'Hoàn tiền',
                    AuditLog::ACTION_REVERSAL => 'Đảo phiếu',
                    AuditLog::ACTION_CANCEL => 'Hủy hóa đơn',
                    AuditLog::ACTION_UPDATE => 'Cập nhật hóa đơn',
                    default => 'Ghi nhận thanh toán',
                };

                $description = $log->metadata['invoice_no'] ?? ($log->metadata['invoice_id'] ?? null);
                $amount = $log->metadata['amount'] ?? null;
                if ($amount !== null) {
                    $amount = number_format((float) $amount, 0, ',', '.');
                }
                $description = $description ? "Hóa đơn {$description}" : 'Giao dịch tài chính';
                $description = $amount !== null ? $description.' • '.$amount.'đ' : $description;

                $activities->push([
                    'date' => $log->created_at,
                    'type' => 'audit',
                    'icon' => 'heroicon-o-shield-check',
                    'color' => match ($log->action) {
                        AuditLog::ACTION_CANCEL => 'danger',
                        AuditLog::ACTION_REFUND => 'warning',
                        AuditLog::ACTION_REVERSAL => 'primary',
                        AuditLog::ACTION_UPDATE => 'info',
                        default => 'success',
                    },
                    'title' => $label,
                    'description' => $description,
                    'meta' => [
                        'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                        'Loại' => $log->entity_type,
                    ],
                    'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
                ]);
            });

        // Audit logs (appointment & care tickets)
        AuditLog::query()
            ->whereIn('entity_type', [AuditLog::ENTITY_APPOINTMENT, AuditLog::ENTITY_CARE_TICKET])
            ->whereIn('action', [
                AuditLog::ACTION_CANCEL,
                AuditLog::ACTION_RESCHEDULE,
                AuditLog::ACTION_NO_SHOW,
                AuditLog::ACTION_COMPLETE,
                AuditLog::ACTION_FOLLOW_UP,
                AuditLog::ACTION_FAIL,
            ])
            ->where('metadata->patient_id', $this->record->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->each(function (AuditLog $log) use ($activities) {
                if ($log->entity_type === AuditLog::ENTITY_APPOINTMENT) {
                    $label = match ($log->action) {
                        AuditLog::ACTION_CANCEL => 'Hủy lịch hẹn',
                        AuditLog::ACTION_RESCHEDULE => 'Hẹn lại lịch',
                        AuditLog::ACTION_NO_SHOW => 'Không đến',
                        AuditLog::ACTION_COMPLETE => 'Hoàn thành lịch hẹn',
                        default => 'Cập nhật lịch hẹn',
                    };

                    $statusLabel = Appointment::statusLabel($log->metadata['status_to'] ?? null);
                    $description = "Trạng thái: {$statusLabel}";

                    $appointmentAt = $log->metadata['appointment_at'] ?? null;
                    if ($appointmentAt) {
                        try {
                            $description = 'Lịch hẹn '.Carbon::parse($appointmentAt)->format('d/m/Y H:i')
                                .' • '.$statusLabel;
                        } catch (\Throwable) {
                            $description = "Lịch hẹn {$appointmentAt} • {$statusLabel}";
                        }
                    }

                    $reason = $log->metadata['reschedule_reason']
                        ?? $log->metadata['cancellation_reason']
                        ?? null;
                    if ($reason) {
                        $description .= ' • '.$reason;
                    }

                    $activities->push([
                        'date' => $log->created_at,
                        'type' => 'audit',
                        'icon' => match ($log->action) {
                            AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                            AuditLog::ACTION_RESCHEDULE => 'heroicon-o-arrow-path',
                            default => 'heroicon-o-calendar',
                        },
                        'color' => match ($log->action) {
                            AuditLog::ACTION_CANCEL => 'danger',
                            AuditLog::ACTION_RESCHEDULE => 'warning',
                            AuditLog::ACTION_NO_SHOW => 'gray',
                            AuditLog::ACTION_COMPLETE => 'success',
                            default => 'info',
                        },
                        'title' => $label,
                        'description' => $description,
                        'meta' => [
                            'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                            'Trạng thái' => $statusLabel,
                        ],
                        'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
                    ]);

                    return;
                }

                if ($log->entity_type !== AuditLog::ENTITY_CARE_TICKET) {
                    return;
                }

                $careTypeLabels = [
                    'warranty' => 'Bảo hành',
                    'recall_recare' => 'Recall / Re-care',
                    'post_treatment_follow_up' => 'Hỏi thăm sau điều trị',
                    'treatment_plan_follow_up' => 'Theo dõi chưa chốt kế hoạch',
                    'appointment_reminder' => 'Nhắc lịch hẹn',
                    'no_show_recovery' => 'Recovery no-show',
                    'payment_reminder' => 'Nhắc thanh toán',
                    'medication_reminder' => 'Nhắc lịch uống thuốc',
                    'birthday_care' => 'Chăm sóc sinh nhật',
                    'general_care' => 'Chăm sóc chung',
                    'other' => 'Khác',
                ];

                $careType = $log->metadata['care_type'] ?? null;
                $careTypeLabel = $careTypeLabels[$careType] ?? 'Chăm sóc';
                $careStatusLabel = Note::careStatusLabel($log->metadata['care_status_to'] ?? null);

                $label = match ($log->action) {
                    AuditLog::ACTION_COMPLETE => 'Hoàn thành chăm sóc',
                    AuditLog::ACTION_FOLLOW_UP => 'Cần chăm sóc lại',
                    AuditLog::ACTION_FAIL => 'Chăm sóc thất bại',
                    default => 'Cập nhật chăm sóc',
                };

                $activities->push([
                    'date' => $log->created_at,
                    'type' => 'audit',
                    'icon' => match ($log->action) {
                        AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                        AuditLog::ACTION_FOLLOW_UP => 'heroicon-o-arrow-path',
                        AuditLog::ACTION_FAIL => 'heroicon-o-x-circle',
                        default => 'heroicon-o-chat-bubble-left-right',
                    },
                    'color' => match ($log->action) {
                        AuditLog::ACTION_COMPLETE => 'success',
                        AuditLog::ACTION_FOLLOW_UP => 'info',
                        AuditLog::ACTION_FAIL => 'danger',
                        default => 'gray',
                    },
                    'title' => $label,
                    'description' => "{$careTypeLabel} • {$careStatusLabel}",
                    'meta' => [
                        'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                        'Loại chăm sóc' => $careTypeLabel,
                        'Trạng thái' => $careStatusLabel,
                    ],
                    'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
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
