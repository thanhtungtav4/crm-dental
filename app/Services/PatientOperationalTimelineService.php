<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\FactoryOrder;
use App\Models\Note;
use App\Models\Patient;
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
            AuditLog::ENTITY_PAYMENT, AuditLog::ENTITY_INVOICE => $this->mapFinancialEntry($log),
            AuditLog::ENTITY_APPOINTMENT => $this->mapAppointmentEntry($log),
            AuditLog::ENTITY_CARE_TICKET => $this->mapCareTicketEntry($log),
            AuditLog::ENTITY_FACTORY_ORDER => $this->mapFactoryOrderEntry($log),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapFinancialEntry(AuditLog $log): array
    {
        $title = match ($log->action) {
            AuditLog::ACTION_REFUND => 'Hoàn tiền',
            AuditLog::ACTION_REVERSAL => 'Đảo phiếu',
            AuditLog::ACTION_CANCEL => 'Hủy hóa đơn',
            AuditLog::ACTION_UPDATE => 'Cập nhật hóa đơn',
            default => 'Giao dịch tài chính',
        };

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

    protected function formatTimestamp(string $value): string
    {
        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }
}
