<?php

namespace App\Services;

use App\Models\ClinicalMediaAsset;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\PatientPhoto;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IntegrationOperationalReadModelService
{
    public function __construct(
        protected IntegrationSecretRotationService $integrationSecretRotationService,
    ) {}

    public function webLeadIngestionRetentionCandidateCount(int $retentionDays): int
    {
        return $this->webLeadIngestionRetentionQuery($retentionDays)
            ->count();
    }

    public function webLeadTerminalEmailRetentionCandidateCount(int $retentionDays): int
    {
        return $this->webLeadTerminalEmailRetentionQuery($retentionDays)
            ->count();
    }

    public function webLeadRetryableEmailCount(): int
    {
        return WebLeadEmailDelivery::query()
            ->where('status', WebLeadEmailDelivery::STATUS_RETRYABLE)
            ->count();
    }

    public function webLeadDeadEmailCount(): int
    {
        return WebLeadEmailDelivery::query()
            ->where('status', WebLeadEmailDelivery::STATUS_DEAD)
            ->count();
    }

    public function zaloWebhookRetentionCandidateCount(int $retentionDays): int
    {
        return $this->zaloWebhookRetentionQuery($retentionDays)
            ->count();
    }

    public function popupDeliveryRetentionCandidateCount(int $retentionDays): int
    {
        return $this->popupDeliveryRetentionQuery($retentionDays)
            ->count();
    }

    public function popupAnnouncementRetentionCandidateCount(int $retentionDays): int
    {
        return $this->popupAnnouncementRetentionQuery($retentionDays)
            ->count();
    }

    public function patientPhotoRetentionCandidateCount(int $retentionDays, bool $includeXray = false): int
    {
        return $this->patientPhotoRetentionQuery($retentionDays, $includeXray)
            ->count();
    }

    public function emrRetentionCandidateCount(int $retentionDays): int
    {
        $logs = $this->emrLogRetentionQuery($retentionDays)->count();
        $events = $this->emrEventRetentionQuery($retentionDays)->count();

        return $logs + $events;
    }

    public function clinicalMediaRetentionCandidateCount(string $retentionClass, int $retentionDays): int
    {
        return $this->clinicalMediaRetentionQuery($retentionClass, $retentionDays)
            ->count();
    }

    public function emrDeadBacklogCount(?int $patientId = null): int
    {
        return EmrSyncEvent::query()
            ->when($patientId !== null, fn (Builder $query) => $query->where('patient_id', $patientId))
            ->where('status', EmrSyncEvent::STATUS_DEAD)
            ->count();
    }

    public function emrFailedBacklogCount(): int
    {
        return EmrSyncEvent::query()
            ->where('status', EmrSyncEvent::STATUS_FAILED)
            ->count();
    }

    public function googleCalendarRetentionCandidateCount(int $retentionDays): int
    {
        $logs = $this->googleCalendarLogRetentionQuery($retentionDays)->count();
        $events = $this->googleCalendarEventRetentionQuery($retentionDays)->count();

        return $logs + $events;
    }

    public function googleCalendarDeadBacklogCount(?int $appointmentId = null): int
    {
        return GoogleCalendarSyncEvent::query()
            ->when($appointmentId !== null, fn (Builder $query) => $query->where('appointment_id', $appointmentId))
            ->where('status', GoogleCalendarSyncEvent::STATUS_DEAD)
            ->count();
    }

    public function googleCalendarFailedBacklogCount(): int
    {
        return GoogleCalendarSyncEvent::query()
            ->where('status', GoogleCalendarSyncEvent::STATUS_FAILED)
            ->count();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     display_name: string,
     *     grace_expires_at: string,
     *     rotated_at: ?string,
     *     rotated_by: ?int,
     *     rotation_reason: ?string,
     *     remaining_minutes: int
     * }>
     */
    public function activeGraceRotations(): Collection
    {
        return $this->integrationSecretRotationService->activeGraceRotations();
    }

    /**
     * @return Collection<int, array{
     *     key:string,
     *     display_name:string,
     *     grace_expires_at_label:string,
     *     remaining_minutes_label:string,
     *     rotation_reason:?string
     * }>
     */
    public function renderedActiveGraceRotations(): Collection
    {
        return $this->activeGraceRotations()
            ->map(function (array $rotation): array {
                return [
                    'key' => (string) $rotation['key'],
                    'display_name' => (string) $rotation['display_name'],
                    'grace_expires_at_label' => \Illuminate\Support\Carbon::parse((string) $rotation['grace_expires_at'])
                        ->format('d/m/Y H:i'),
                    'remaining_minutes_label' => 'Còn lại khoảng '.((int) $rotation['remaining_minutes']).' phút.',
                    'rotation_reason' => filled($rotation['rotation_reason'] ?? null)
                        ? (string) $rotation['rotation_reason']
                        : null,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     display_name: string,
     *     grace_expires_at: string,
     *     expired_minutes: int
     * }>
     */
    public function expiredGraceRotations(): Collection
    {
        return $this->integrationSecretRotationService->expiredGraceRotations();
    }

    /**
     * @return Collection<int, array{
     *     key:string,
     *     display_name:string,
     *     grace_expires_at_label:string,
     *     expired_minutes_label:string
     * }>
     */
    public function renderedExpiredGraceRotations(): Collection
    {
        return $this->expiredGraceRotations()
            ->map(function (array $rotation): array {
                return [
                    'key' => (string) $rotation['key'],
                    'display_name' => (string) $rotation['display_name'],
                    'grace_expires_at_label' => \Illuminate\Support\Carbon::parse((string) $rotation['grace_expires_at'])
                        ->format('d/m/Y H:i'),
                    'expired_minutes_label' => 'Quá hạn '.((int) $rotation['expired_minutes']).' phút.',
                ];
            })
            ->values();
    }

    /**
     * @return array<int, array{
     *     label:string,
     *     retention_days:int,
     *     total:int,
     *     description:string,
     *     tone:string
     * }>
     */
    public function retentionCandidates(): array
    {
        return [
            $this->retentionCandidate(
                label: 'Web lead ingestion',
                retentionDays: ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
                total: $this->webLeadIngestionRetentionCandidateCount(
                    ClinicRuntimeSettings::webLeadOperationalRetentionDays()
                ),
                description: 'Lead ingestion log quá hạn retention.',
            ),
            $this->retentionCandidate(
                label: 'Web lead internal email deliveries',
                retentionDays: ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
                total: $this->webLeadTerminalEmailRetentionCandidateCount(
                    ClinicRuntimeSettings::webLeadOperationalRetentionDays()
                ),
                description: 'Delivery log email nội bộ đã terminal và quá hạn review window.',
            ),
            $this->retentionCandidate(
                label: 'Zalo webhook',
                retentionDays: ClinicRuntimeSettings::zaloWebhookRetentionDays(),
                total: $this->zaloWebhookRetentionCandidateCount(
                    ClinicRuntimeSettings::zaloWebhookRetentionDays()
                ),
                description: 'Webhook inbound đã quá hạn retention.',
            ),
            $this->retentionCandidate(
                label: 'EMR outbox',
                retentionDays: ClinicRuntimeSettings::emrOperationalRetentionDays(),
                total: $this->emrRetentionCandidateCount(
                    ClinicRuntimeSettings::emrOperationalRetentionDays()
                ),
                description: 'EMR log + event đã đồng bộ xong và đủ điều kiện prune.',
            ),
            $this->retentionCandidate(
                label: 'Clinical media temporary',
                retentionDays: ClinicRuntimeSettings::clinicalMediaRetentionDays(ClinicalMediaAsset::RETENTION_TEMPORARY),
                total: ClinicRuntimeSettings::clinicalMediaRetentionEnabled()
                    ? $this->clinicalMediaRetentionCandidateCount(
                        ClinicalMediaAsset::RETENTION_TEMPORARY,
                        ClinicRuntimeSettings::clinicalMediaRetentionDays(ClinicalMediaAsset::RETENTION_TEMPORARY)
                    )
                    : 0,
                description: 'Clinical media retention class temporary đã đủ điều kiện prune.',
            ),
            $this->retentionCandidate(
                label: 'Clinical media operational',
                retentionDays: ClinicRuntimeSettings::clinicalMediaRetentionDays(ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL),
                total: ClinicRuntimeSettings::clinicalMediaRetentionEnabled()
                    ? $this->clinicalMediaRetentionCandidateCount(
                        ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
                        ClinicRuntimeSettings::clinicalMediaRetentionDays(ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL)
                    )
                    : 0,
                description: 'Clinical media retention class clinical_operational đã đủ điều kiện prune.',
            ),
            $this->retentionCandidate(
                label: 'Google Calendar outbox',
                retentionDays: ClinicRuntimeSettings::googleCalendarOperationalRetentionDays(),
                total: $this->googleCalendarRetentionCandidateCount(
                    ClinicRuntimeSettings::googleCalendarOperationalRetentionDays()
                ),
                description: 'Google Calendar log + event đã đủ điều kiện prune.',
            ),
            $this->retentionCandidate(
                label: 'Popup announcement logs',
                retentionDays: ClinicRuntimeSettings::popupAnnouncementRetentionDays(),
                total: $this->popupDeliveryRetentionCandidateCount(
                    ClinicRuntimeSettings::popupAnnouncementRetentionDays()
                ) + $this->popupAnnouncementRetentionCandidateCount(
                    ClinicRuntimeSettings::popupAnnouncementRetentionDays()
                ),
                description: 'Popup delivery và announcement log đã quá hạn retention.',
            ),
            $this->retentionCandidate(
                label: 'Patient photos',
                retentionDays: ClinicRuntimeSettings::patientPhotoRetentionDays(),
                total: ClinicRuntimeSettings::patientPhotoRetentionEnabled()
                    ? $this->patientPhotoRetentionCandidateCount(
                        ClinicRuntimeSettings::patientPhotoRetentionDays(),
                        ClinicRuntimeSettings::patientPhotoRetentionIncludeXray()
                    )
                    : 0,
                description: 'Ảnh bệnh nhân đã quá hạn retention theo runtime setting hiện tại.',
            ),
        ];
    }

    /**
     * @return array{
     *     total:int,
     *     keys:array<int, string>,
     *     display_names:array<int, string>,
     *     max_expired_minutes:int
     * }
     */
    public function expiredGraceRotationSummary(): array
    {
        $rotations = $this->expiredGraceRotations()->values();

        return [
            'total' => $rotations->count(),
            'keys' => $rotations->pluck('key')->map(fn (mixed $key): string => (string) $key)->values()->all(),
            'display_names' => $rotations->pluck('display_name')->map(fn (mixed $label): string => (string) $label)->values()->all(),
            'max_expired_minutes' => (int) $rotations
                ->pluck('expired_minutes')
                ->map(fn (mixed $minutes): int => (int) $minutes)
                ->max(),
        ];
    }

    public function webLeadIngestionRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return WebLeadIngestion::query()
            ->whereIn('status', [
                WebLeadIngestion::STATUS_CREATED,
                WebLeadIngestion::STATUS_MERGED,
                WebLeadIngestion::STATUS_FAILED,
            ])
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('processed_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('processed_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            });
    }

    public function webLeadTerminalEmailRetentionQuery(int $retentionDays): Builder
    {
        return WebLeadEmailDelivery::query()
            ->whereIn('status', [
                WebLeadEmailDelivery::STATUS_SENT,
                WebLeadEmailDelivery::STATUS_DEAD,
                WebLeadEmailDelivery::STATUS_SKIPPED,
            ])
            ->where('updated_at', '<', now()->subDays($retentionDays));
    }

    public function zaloWebhookRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return ZaloWebhookEvent::query()
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('received_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('received_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });
    }

    public function popupDeliveryRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return PopupAnnouncementDelivery::query()
            ->whereIn('status', [
                PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED,
                PopupAnnouncementDelivery::STATUS_DISMISSED,
                PopupAnnouncementDelivery::STATUS_EXPIRED,
            ])
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where('acknowledged_at', '<', $cutoff)
                    ->orWhere('dismissed_at', '<', $cutoff)
                    ->orWhere('expired_at', '<', $cutoff)
                    ->orWhere(function (Builder $nested) use ($cutoff): void {
                        $nested
                            ->whereNull('acknowledged_at')
                            ->whereNull('dismissed_at')
                            ->whereNull('expired_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            });
    }

    public function popupAnnouncementRetentionQuery(int $retentionDays): Builder
    {
        return PopupAnnouncement::query()
            ->whereIn('status', [
                PopupAnnouncement::STATUS_CANCELLED,
                PopupAnnouncement::STATUS_EXPIRED,
            ])
            ->where('updated_at', '<', now()->subDays($retentionDays))
            ->whereDoesntHave('deliveries');
    }

    public function patientPhotoRetentionQuery(int $retentionDays, bool $includeXray = false): Builder
    {
        return PatientPhoto::query()
            ->whereIn('type', $this->patientPhotoRetentionTypes($includeXray))
            ->whereDate('date', '<', now()->subDays($retentionDays)->startOfDay()->toDateString());
    }

    public function emrLogRetentionQuery(int $retentionDays): Builder
    {
        return EmrSyncLog::query()
            ->where('attempted_at', '<', now()->subDays($retentionDays));
    }

    public function emrEventRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return EmrSyncEvent::query()
            ->whereIn('status', [
                EmrSyncEvent::STATUS_SYNCED,
                EmrSyncEvent::STATUS_DEAD,
            ])
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('processed_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('processed_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            });
    }

    public function clinicalMediaRetentionQuery(string $retentionClass, int $retentionDays): Builder
    {
        return ClinicalMediaAsset::query()
            ->whereNull('deleted_at')
            ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
            ->where('legal_hold', false)
            ->where('retention_class', strtolower(trim($retentionClass)))
            ->where('captured_at', '<=', now()->subDays($retentionDays));
    }

    public function googleCalendarLogRetentionQuery(int $retentionDays): Builder
    {
        return GoogleCalendarSyncLog::query()
            ->where('attempted_at', '<', now()->subDays($retentionDays));
    }

    public function googleCalendarEventRetentionQuery(int $retentionDays): Builder
    {
        $cutoff = now()->subDays($retentionDays);

        return GoogleCalendarSyncEvent::query()
            ->whereIn('status', [
                GoogleCalendarSyncEvent::STATUS_SYNCED,
                GoogleCalendarSyncEvent::STATUS_DEAD,
            ])
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('processed_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('processed_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            });
    }

    /**
     * @return array<int, string>
     */
    protected function patientPhotoRetentionTypes(bool $includeXray): array
    {
        $types = [
            PatientPhoto::TYPE_NORMAL,
            PatientPhoto::TYPE_EXTERNAL,
            PatientPhoto::TYPE_INTERNAL,
        ];

        if ($includeXray) {
            $types[] = PatientPhoto::TYPE_XRAY;
        }

        return $types;
    }

    /**
     * @return array{
     *     label:string,
     *     retention_days:int,
     *     total:int,
     *     description:string,
     *     tone:string
     * }
     */
    protected function retentionCandidate(
        string $label,
        int $retentionDays,
        int $total,
        string $description,
    ): array {
        return [
            'label' => $label,
            'retention_days' => $retentionDays,
            'total' => $total,
            'description' => $description,
            'tone' => $total > 0 ? 'warning' : 'success',
        ];
    }
}
