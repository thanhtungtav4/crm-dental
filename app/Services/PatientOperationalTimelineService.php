<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BranchTransferRequest;
use App\Models\FactoryOrder;
use App\Models\InsuranceClaim;
use App\Models\MaterialIssueNote;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PlanItem;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PatientOperationalTimelineService
{
    public function timelineEntriesForPatient(Patient|int $patient, int $limit = 20): Collection
    {
        $patientId = $patient instanceof Patient ? (int) $patient->getKey() : (int) $patient;

        if ($patientId <= 0) {
            return collect();
        }

        return AuditLog::query()
            ->with('actor:id,name')
            ->where(function (Builder $query) use ($patientId): void {
                $query->where('patient_id', $patientId)
                    ->orWhere('metadata->patient_id', $patientId);
            })
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereIn('entity_type', [AuditLog::ENTITY_PAYMENT, AuditLog::ENTITY_INVOICE])
                        ->whereIn('action', [
                            AuditLog::ACTION_REFUND,
                            AuditLog::ACTION_REVERSAL,
                            AuditLog::ACTION_CANCEL,
                            AuditLog::ACTION_UPDATE,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_RECEIPT_EXPENSE)
                        ->whereIn('action', [
                            AuditLog::ACTION_APPROVE,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                            AuditLog::ACTION_UPDATE,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_MATERIAL_ISSUE_NOTE)
                        ->whereIn('action', [
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->whereIn('entity_type', [AuditLog::ENTITY_APPOINTMENT, AuditLog::ENTITY_CARE_TICKET])
                        ->whereIn('action', [
                            AuditLog::ACTION_CANCEL,
                            AuditLog::ACTION_RESCHEDULE,
                            AuditLog::ACTION_NO_SHOW,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_FOLLOW_UP,
                            AuditLog::ACTION_FAIL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_FACTORY_ORDER)
                        ->whereIn('action', [
                            AuditLog::ACTION_UPDATE,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_INSURANCE_CLAIM)
                        ->whereIn('action', [
                            AuditLog::ACTION_APPROVE,
                            AuditLog::ACTION_FAIL,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_PLAN_ITEM)
                        ->whereIn('action', [
                            AuditLog::ACTION_UPDATE,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_TREATMENT_SESSION)
                        ->whereIn('action', [
                            AuditLog::ACTION_UPDATE,
                            AuditLog::ACTION_COMPLETE,
                            AuditLog::ACTION_CANCEL,
                        ]);
                })->orWhere(function (Builder $query): void {
                    $query->where('entity_type', AuditLog::ENTITY_BRANCH_TRANSFER)
                        ->whereIn('action', [
                            AuditLog::ACTION_CREATE,
                            AuditLog::ACTION_TRANSFER,
                            AuditLog::ACTION_FAIL,
                            AuditLog::ACTION_CANCEL,
                        ]);
                });
            })
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): ?array => $this->mapAuditLogEntry($log))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function mapAuditLogEntry(AuditLog $log): ?array
    {
        return match ($log->entity_type) {
            AuditLog::ENTITY_PAYMENT, AuditLog::ENTITY_INVOICE, AuditLog::ENTITY_RECEIPT_EXPENSE => $this->mapFinancialEntry($log),
            AuditLog::ENTITY_MATERIAL_ISSUE_NOTE => $this->mapMaterialIssueNoteEntry($log),
            AuditLog::ENTITY_APPOINTMENT => $this->mapAppointmentEntry($log),
            AuditLog::ENTITY_CARE_TICKET => $this->mapCareTicketEntry($log),
            AuditLog::ENTITY_FACTORY_ORDER => $this->mapFactoryOrderEntry($log),
            AuditLog::ENTITY_INSURANCE_CLAIM => $this->mapInsuranceClaimEntry($log),
            AuditLog::ENTITY_PLAN_ITEM => $this->mapPlanItemEntry($log),
            AuditLog::ENTITY_TREATMENT_SESSION => $this->mapTreatmentSessionEntry($log),
            AuditLog::ENTITY_BRANCH_TRANSFER => $this->mapBranchTransferEntry($log),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapFinancialEntry(AuditLog $log): array
    {
        if ($log->entity_type === AuditLog::ENTITY_RECEIPT_EXPENSE) {
            $title = match ($log->action) {
                AuditLog::ACTION_APPROVE => 'Duyệt phiếu thu/chi',
                AuditLog::ACTION_COMPLETE => 'Hạch toán phiếu thu/chi',
                AuditLog::ACTION_CANCEL => 'Hủy phiếu thu/chi',
                default => 'Cập nhật phiếu thu/chi',
            };
        } elseif ($log->entity_type === AuditLog::ENTITY_PAYMENT) {
            $title = match ($log->action) {
                AuditLog::ACTION_CREATE => 'Ghi nhận thanh toán',
                AuditLog::ACTION_REFUND => 'Hoàn tiền',
                AuditLog::ACTION_REVERSAL => 'Đảo phiếu thu',
                AuditLog::ACTION_CANCEL => 'Hủy phiếu thu',
                default => 'Cập nhật thanh toán',
            };
        } else {
            $title = match ($log->action) {
                AuditLog::ACTION_REFUND => 'Hoàn tiền',
                AuditLog::ACTION_REVERSAL => 'Đảo phiếu',
                AuditLog::ACTION_CANCEL => 'Hủy hóa đơn',
                AuditLog::ACTION_UPDATE => 'Cập nhật hóa đơn',
                default => 'Giao dịch tài chính',
            };
        }

        $description = $this->buildFinancialDescription($log);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => 'heroicon-o-shield-check',
            'color' => match ($log->action) {
                AuditLog::ACTION_CANCEL => 'danger',
                AuditLog::ACTION_REFUND => 'warning',
                AuditLog::ACTION_REVERSAL => 'primary',
                AuditLog::ACTION_UPDATE => 'info',
                default => 'success',
            },
            'title' => $title,
            'description' => $description,
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Loại' => $log->entity_type,
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAppointmentEntry(AuditLog $log): array
    {
        $title = match ($log->action) {
            AuditLog::ACTION_CANCEL => 'Hủy lịch hẹn',
            AuditLog::ACTION_RESCHEDULE => 'Hẹn lại lịch',
            AuditLog::ACTION_NO_SHOW => 'Không đến',
            AuditLog::ACTION_COMPLETE => 'Hoàn thành lịch hẹn',
            default => 'Cập nhật lịch hẹn',
        };

        $statusLabel = Appointment::statusLabel(data_get($log->metadata, 'status_to'));
        $description = $this->buildAppointmentDescription($log, $statusLabel);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
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
            'title' => $title,
            'description' => $description,
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => $statusLabel,
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapCareTicketEntry(AuditLog $log): array
    {
        $careTypeLabels = ClinicRuntimeSettings::careTypeDisplayOptions();
        $careType = data_get($log->metadata, 'care_type');
        $careTypeLabel = $careTypeLabels[$careType] ?? 'Chăm sóc';
        $careStatusLabel = Note::careStatusLabel(data_get($log->metadata, 'care_status_to'));

        $title = match ($log->action) {
            AuditLog::ACTION_COMPLETE => 'Hoàn thành chăm sóc',
            AuditLog::ACTION_FOLLOW_UP => 'Cần chăm sóc lại',
            AuditLog::ACTION_FAIL => 'Chăm sóc thất bại',
            default => 'Cập nhật chăm sóc',
        };

        return [
            'date' => $log->occurred_at ?? $log->created_at,
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
            'title' => $title,
            'description' => "{$careTypeLabel} • {$careStatusLabel}",
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Loại chăm sóc' => $careTypeLabel,
                'Trạng thái' => $careStatusLabel,
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapFactoryOrderEntry(AuditLog $log): array
    {
        $statusTo = (string) data_get($log->metadata, 'status_to', '');
        $title = match ($log->action) {
            AuditLog::ACTION_COMPLETE => 'Hoàn thành labo',
            AuditLog::ACTION_CANCEL => 'Hủy lệnh labo',
            AuditLog::ACTION_UPDATE => $statusTo === FactoryOrder::STATUS_ORDERED
                ? 'Đặt labo'
                : ($statusTo === FactoryOrder::STATUS_IN_PROGRESS ? 'Labo đang thực hiện' : 'Cập nhật lệnh labo'),
            default => 'Cập nhật lệnh labo',
        };

        $statusLabel = FactoryOrder::statusOptions()[$statusTo] ?? 'Lệnh labo';
        $descriptionParts = array_filter([
            data_get($log->metadata, 'order_no') ?: 'Lệnh #'.data_get($log->metadata, 'factory_order_id', $log->entity_id),
            $statusLabel,
            data_get($log->metadata, 'supplier_name'),
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-building-office-2',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'success',
                AuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => $statusLabel,
            ],
            'url' => route('filament.admin.resources.factory-orders.edit', ['record' => $log->entity_id]),
        ];
    }

    protected function buildFinancialDescription(AuditLog $log): string
    {
        if ($log->entity_type === AuditLog::ENTITY_RECEIPT_EXPENSE) {
            $description = data_get($log->metadata, 'voucher_code') ?: 'Phiếu thu/chi';
            $amount = data_get($log->metadata, 'amount');

            if ($amount !== null) {
                $amount = number_format((float) $amount, 0, ',', '.');
            }

            return $amount !== null ? "Phiếu {$description} • {$amount}đ" : "Phiếu {$description}";
        }

        if ($log->entity_type === AuditLog::ENTITY_PAYMENT) {
            $description = data_get($log->metadata, 'invoice_no') ?? data_get($log->metadata, 'invoice_id');
            $amount = data_get($log->metadata, 'amount');

            if ($amount !== null) {
                $amount = number_format(abs((float) $amount), 0, ',', '.');
            }

            $description = $description ? "Hóa đơn {$description}" : 'Thanh toán';

            if ($amount !== null) {
                $description .= ' • '.$amount.'đ';
            }

            $refundReason = data_get($log->metadata, 'reason') ?? data_get($log->metadata, 'refund_reason');
            if (filled($refundReason)) {
                $description .= ' • '.trim((string) $refundReason);
            }

            return $description;
        }

        $description = data_get($log->metadata, 'invoice_no') ?? data_get($log->metadata, 'invoice_id');
        $amount = data_get($log->metadata, 'amount');

        if ($amount !== null) {
            $amount = number_format((float) $amount, 0, ',', '.');
        }

        $description = $description ? "Hóa đơn {$description}" : 'Giao dịch tài chính';

        return $amount !== null ? $description.' • '.$amount.'đ' : $description;
    }

    protected function buildAppointmentDescription(AuditLog $log, string $statusLabel): string
    {
        $description = "Trạng thái: {$statusLabel}";
        $appointmentAt = data_get($log->metadata, 'appointment_at');

        if (filled($appointmentAt)) {
            $description = 'Lịch hẹn '.$this->formatTimestamp((string) $appointmentAt).' • '.$statusLabel;
        }

        $reason = data_get($log->metadata, 'reason')
            ?? data_get($log->metadata, 'reschedule_reason')
            ?? data_get($log->metadata, 'cancellation_reason');

        if (filled($reason)) {
            $description .= ' • '.trim((string) $reason);
        }

        return $description;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapInsuranceClaimEntry(AuditLog $log): array
    {
        $statusTo = (string) data_get($log->metadata, 'status_to', '');
        $title = match ($log->action) {
            AuditLog::ACTION_APPROVE => 'Bảo hiểm đã duyệt',
            AuditLog::ACTION_FAIL => 'Bảo hiểm từ chối',
            AuditLog::ACTION_COMPLETE => 'Bảo hiểm đã thanh toán',
            AuditLog::ACTION_CANCEL => 'Hủy hồ sơ bảo hiểm',
            default => 'Cập nhật hồ sơ bảo hiểm',
        };

        $descriptionParts = array_filter([
            data_get($log->metadata, 'claim_number') ?: 'Claim #'.$log->entity_id,
            $this->insuranceClaimStatusLabel($statusTo),
            $this->insuranceClaimAmountLabel($log),
            data_get($log->metadata, 'denial_reason_code'),
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_APPROVE, AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                AuditLog::ACTION_FAIL, AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-shield-check',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_APPROVE, AuditLog::ACTION_COMPLETE => 'success',
                AuditLog::ACTION_FAIL, AuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => $this->insuranceClaimStatusLabel($statusTo),
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapTreatmentSessionEntry(AuditLog $log): array
    {
        $statusTo = (string) data_get($log->metadata, 'status_to', '');
        $title = match ($log->action) {
            AuditLog::ACTION_COMPLETE => 'Hoàn thành buổi điều trị',
            AuditLog::ACTION_CANCEL => 'Hủy buổi điều trị',
            default => 'Cập nhật buổi điều trị',
        };

        $descriptionParts = array_filter([
            data_get($log->metadata, 'plan_item_id') ? 'Hạng mục #'.data_get($log->metadata, 'plan_item_id') : null,
            $this->treatmentSessionStatusLabel($statusTo),
            filled(data_get($log->metadata, 'performed_at'))
                ? 'Thực hiện '.$this->formatTimestamp((string) data_get($log->metadata, 'performed_at'))
                : null,
        ]);

        if (filled(data_get($log->metadata, 'reason'))) {
            $descriptionParts[] = (string) data_get($log->metadata, 'reason');
        }

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-wrench-screwdriver',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'success',
                AuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => $this->treatmentSessionStatusLabel($statusTo),
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapPlanItemEntry(AuditLog $log): array
    {
        $statusTo = (string) data_get($log->metadata, 'status_to', '');
        $title = match ($log->action) {
            AuditLog::ACTION_COMPLETE => 'Hoàn thành hạng mục điều trị',
            AuditLog::ACTION_CANCEL => 'Hủy hạng mục điều trị',
            default => $statusTo === PlanItem::STATUS_IN_PROGRESS
                ? 'Bắt đầu hạng mục điều trị'
                : 'Cập nhật hạng mục điều trị',
        };

        $descriptionParts = array_filter([
            (string) data_get($log->metadata, 'plan_item_name', ''),
            filled($statusTo) ? PlanItem::statusLabel($statusTo) : null,
            data_get($log->metadata, 'completed_visits_to') !== null && data_get($log->metadata, 'required_visits')
                ? 'Lần khám '.data_get($log->metadata, 'completed_visits_to').'/'.data_get($log->metadata, 'required_visits')
                : null,
            filled(data_get($log->metadata, 'reason')) ? (string) data_get($log->metadata, 'reason') : null,
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'heroicon-o-check-badge',
                AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-play',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'success',
                AuditLog::ACTION_CANCEL => 'danger',
                default => 'warning',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => filled($statusTo) ? PlanItem::statusLabel($statusTo) : 'Không xác định',
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMaterialIssueNoteEntry(AuditLog $log): array
    {
        $title = match ($log->action) {
            AuditLog::ACTION_COMPLETE => 'Xuất vật tư',
            AuditLog::ACTION_CANCEL => 'Hủy phiếu xuất kho',
            default => 'Cập nhật phiếu xuất kho',
        };

        $itemCount = data_get($log->metadata, 'item_count');
        $descriptionParts = array_filter([
            data_get($log->metadata, 'note_no') ? 'Phiếu '.data_get($log->metadata, 'note_no') : 'Phiếu xuất kho',
            is_numeric($itemCount) ? ((int) $itemCount).' vật tư' : null,
            filled(data_get($log->metadata, 'reason')) ? (string) data_get($log->metadata, 'reason') : null,
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'heroicon-o-archive-box-arrow-down',
                AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-archive-box',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_COMPLETE => 'success',
                AuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => match (data_get($log->metadata, 'status_to')) {
                    MaterialIssueNote::STATUS_POSTED => 'Đã xuất kho',
                    MaterialIssueNote::STATUS_CANCELLED => 'Đã hủy',
                    default => 'Phiếu xuất kho',
                },
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapBranchTransferEntry(AuditLog $log): array
    {
        $statusTo = (string) data_get($log->metadata, 'status_to', '');
        $title = match ($log->action) {
            AuditLog::ACTION_CREATE => 'Tạo yêu cầu chuyển chi nhánh',
            AuditLog::ACTION_TRANSFER => 'Áp dụng chuyển chi nhánh',
            AuditLog::ACTION_FAIL => 'Từ chối chuyển chi nhánh',
            AuditLog::ACTION_CANCEL => 'Hủy yêu cầu chuyển chi nhánh',
            default => 'Cập nhật chuyển chi nhánh',
        };

        $descriptionParts = array_filter([
            $this->branchTransferRouteLabel($log),
            filled(data_get($log->metadata, 'reason')) ? (string) data_get($log->metadata, 'reason') : null,
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_TRANSFER => 'heroicon-o-arrow-right-circle',
                AuditLog::ACTION_FAIL, AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                default => 'heroicon-o-arrows-right-left',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_TRANSFER => 'success',
                AuditLog::ACTION_FAIL, AuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => implode(' • ', $descriptionParts),
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
                'Trạng thái' => BranchTransferRequest::statusLabel($statusTo),
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    protected function formatTimestamp(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function insuranceClaimStatusLabel(string $status): string
    {
        return match ($status) {
            InsuranceClaim::STATUS_DRAFT => 'Nháp',
            InsuranceClaim::STATUS_SUBMITTED => 'Đã gửi',
            InsuranceClaim::STATUS_APPROVED => 'Đã duyệt',
            InsuranceClaim::STATUS_DENIED => 'Từ chối',
            InsuranceClaim::STATUS_RESUBMITTED => 'Gửi lại',
            InsuranceClaim::STATUS_PAID => 'Đã thanh toán',
            InsuranceClaim::STATUS_CANCELLED => 'Đã hủy',
            default => 'Hồ sơ bảo hiểm',
        };
    }

    protected function insuranceClaimAmountLabel(AuditLog $log): ?string
    {
        $amount = data_get($log->metadata, 'amount_approved')
            ?? data_get($log->metadata, 'amount_claimed');

        if (! is_numeric($amount)) {
            return null;
        }

        return number_format((float) $amount, 0, ',', '.').'đ';
    }

    protected function treatmentSessionStatusLabel(string $status): string
    {
        return match ($status) {
            'scheduled' => 'Đã lên lịch',
            'in_progress' => 'Đang thực hiện',
            'done', 'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
            default => 'Buổi điều trị',
        };
    }

    protected function branchTransferRouteLabel(AuditLog $log): string
    {
        $fromBranch = data_get($log->metadata, 'from_branch_name')
            ?: (data_get($log->metadata, 'from_branch_id') ? 'CN #'.data_get($log->metadata, 'from_branch_id') : null);
        $toBranch = data_get($log->metadata, 'to_branch_name')
            ?: (data_get($log->metadata, 'to_branch_id') ? 'CN #'.data_get($log->metadata, 'to_branch_id') : null);

        if ($fromBranch && $toBranch) {
            return $fromBranch.' -> '.$toBranch;
        }

        return 'Điều phối liên chi nhánh';
    }
}
