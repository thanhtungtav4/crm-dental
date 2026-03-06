<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmrAuditLog;
use App\Models\Patient;
use Illuminate\Support\Collection;

class ClinicalAuditTimelineService
{
    public function timelineEntriesForPatient(Patient|int $patient, int $limit = 10): Collection
    {
        $patientId = $patient instanceof Patient ? (int) $patient->id : (int) $patient;

        if ($patientId <= 0) {
            return collect();
        }

        $consentEntries = AuditLog::query()
            ->with('actor:id,name')
            ->where('entity_type', AuditLog::ENTITY_CONSENT)
            ->where(function ($query) use ($patientId): void {
                $query->where('patient_id', $patientId)
                    ->orWhere('metadata->patient_id', $patientId);
            })
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): array => $this->mapAuditLogEntry($log));

        $emrEntries = EmrAuditLog::query()
            ->with('actor:id,name')
            ->forPatient($patientId)
            ->whereIn('entity_type', [
                EmrAuditLog::ENTITY_CLINICAL_NOTE,
                EmrAuditLog::ENTITY_CLINICAL_ORDER,
                EmrAuditLog::ENTITY_CLINICAL_RESULT,
                EmrAuditLog::ENTITY_PHI_ACCESS,
            ])
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (EmrAuditLog $log): array => $this->mapEmrAuditLogEntry($log));

        return $consentEntries
            ->concat($emrEntries)
            ->sortByDesc('date')
            ->take($limit)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapAuditLogEntry(AuditLog $log): array
    {
        $title = match ($log->action) {
            AuditLog::ACTION_APPROVE => 'Consent đã ký',
            AuditLog::ACTION_CANCEL => 'Consent đã thu hồi',
            AuditLog::ACTION_FAIL => 'Consent hết hạn',
            AuditLog::ACTION_CREATE => 'Tạo consent',
            default => 'Cập nhật consent',
        };

        $descriptionParts = array_filter([
            data_get($log->metadata, 'consent_type'),
            data_get($log->metadata, 'status_to'),
            data_get($log->metadata, 'consent_version') ? 'v'.data_get($log->metadata, 'consent_version') : null,
        ]);

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'clinical_audit',
            'icon' => match ($log->action) {
                AuditLog::ACTION_APPROVE => 'heroicon-o-check-badge',
                AuditLog::ACTION_CANCEL => 'heroicon-o-no-symbol',
                AuditLog::ACTION_FAIL => 'heroicon-o-clock',
                default => 'heroicon-o-document-check',
            },
            'color' => match ($log->action) {
                AuditLog::ACTION_APPROVE => 'success',
                AuditLog::ACTION_CANCEL => 'danger',
                AuditLog::ACTION_FAIL => 'warning',
                default => 'info',
            },
            'title' => $title,
            'description' => $descriptionParts !== []
                ? implode(' • ', $descriptionParts)
                : 'Consent lâm sàng',
            'meta' => [
                'Nguồn audit' => 'AuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
            ],
            'url' => route('filament.admin.resources.audit-logs.view', ['record' => $log->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapEmrAuditLogEntry(EmrAuditLog $log): array
    {
        [$title, $description] = match ($log->entity_type) {
            EmrAuditLog::ENTITY_CLINICAL_ORDER => [
                'Chỉ định cận lâm sàng',
                $this->buildEmrDescription(
                    code: data_get($log->context, 'order_code'),
                    type: data_get($log->context, 'order_type'),
                    status: data_get($log->context, 'status_to') ?? data_get($log->context, 'status'),
                ),
            ],
            EmrAuditLog::ENTITY_CLINICAL_RESULT => [
                'Kết quả cận lâm sàng',
                $this->buildEmrDescription(
                    code: data_get($log->context, 'result_code'),
                    type: null,
                    status: data_get($log->context, 'status_to') ?? data_get($log->context, 'status'),
                ),
            ],
            EmrAuditLog::ENTITY_PHI_ACCESS => [
                'Truy cập PHI',
                data_get($log->context, 'target', 'Đã truy cập dữ liệu nhạy cảm'),
            ],
            default => [
                'Phiếu khám lâm sàng',
                $this->buildEmrDescription(
                    code: data_get($log->context, 'note_code'),
                    type: null,
                    status: $log->action,
                ),
            ],
        };

        return [
            'date' => $log->occurred_at ?? $log->created_at,
            'type' => 'clinical_audit',
            'icon' => match ($log->entity_type) {
                EmrAuditLog::ENTITY_CLINICAL_ORDER => 'heroicon-o-beaker',
                EmrAuditLog::ENTITY_CLINICAL_RESULT => 'heroicon-o-clipboard-document-check',
                EmrAuditLog::ENTITY_PHI_ACCESS => 'heroicon-o-shield-check',
                default => 'heroicon-o-document-text',
            },
            'color' => match ($log->action) {
                EmrAuditLog::ACTION_FINALIZE, EmrAuditLog::ACTION_COMPLETE => 'success',
                EmrAuditLog::ACTION_AMEND, EmrAuditLog::ACTION_UPDATE => 'warning',
                EmrAuditLog::ACTION_FAIL, EmrAuditLog::ACTION_CANCEL => 'danger',
                default => 'info',
            },
            'title' => $title,
            'description' => $description,
            'meta' => [
                'Nguồn audit' => 'EmrAuditLog',
                'Người thực hiện' => $log->actor?->name ?? 'Hệ thống',
            ],
            'url' => null,
        ];
    }

    protected function buildEmrDescription(?string $code, ?string $type, ?string $status): string
    {
        $parts = array_filter([$code, $type, $status]);

        return $parts !== [] ? implode(' • ', $parts) : 'Sự kiện lâm sàng';
    }
}
