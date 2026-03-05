<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmrAuditLog;
use App\Models\EmrPatientMap;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Services\EmrAuditLogger;
use App\Services\EmrIntegrationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEmrEvents extends Command
{
    protected $signature = 'emr:sync-events
        {--limit=100 : Số lượng event tối đa mỗi lần chạy}
        {--patient_id= : Chỉ sync cho một bệnh nhân}
        {--dry-run : Chỉ preview, không ghi dữ liệu}
        {--strict-exit : Trả về exit code lỗi nếu vẫn còn failed/dead-letter sau khi chạy}';

    protected $description = 'Đồng bộ outbox EMR (CRM -> EMR) theo cơ chế retry + dead-letter.';

    public function __construct(
        protected EmrIntegrationService $emrIntegrationService,
        protected EmrAuditLogger $emrAuditLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation EMR sync.',
        );
        ActionGate::authorize(
            ActionPermission::EMR_SYNC_PUSH,
            'Bạn không có quyền đẩy dữ liệu đồng bộ EMR.',
        );

        if (! ClinicRuntimeSettings::isEmrEnabled()) {
            $this->warn('EMR integration đang tắt. Không có dữ liệu cần sync.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $patientId = $this->option('patient_id') ? (int) $this->option('patient_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $strictExit = (bool) $this->option('strict-exit');

        if (! $dryRun) {
            $reclaimed = EmrSyncEvent::reclaimStaleProcessing();
            if ($reclaimed > 0) {
                $this->warn("Đã reclaim {$reclaimed} event EMR bị kẹt trạng thái processing.");
            }
        }

        $query = EmrSyncEvent::query()
            ->ready()
            ->orderBy('id')
            ->limit($limit);

        if ($patientId) {
            $query->where('patient_id', $patientId);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('Không có event EMR nào sẵn sàng để xử lý.');

            if (! $dryRun) {
                $deadBacklog = $this->countDeadLetters($patientId);
                $this->recordDeadLetterAlertIfNeeded(
                    deadBacklog: $deadBacklog,
                    deadInBatch: 0,
                    scopeMetadata: ['patient_id' => $patientId],
                );

                if ($strictExit && $deadBacklog > 0) {
                    $this->error("STRICT_EXIT_STATUS: FAIL. EMR outbox còn {$deadBacklog} dead-letter event.");

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        $dead = 0;

        foreach ($events as $event) {
            if ($dryRun) {
                $this->line("DRY RUN - would sync event #{$event->id} ({$event->event_type}) for patient {$event->patient_id}");

                continue;
            }

            $result = $this->processEvent((int) $event->id);

            if ($result === EmrSyncEvent::STATUS_SYNCED) {
                $synced++;
            } elseif ($result === EmrSyncEvent::STATUS_DEAD) {
                $dead++;
            } else {
                $failed++;
            }
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] EMR sync processed. synced={$synced}, failed={$failed}, dead={$dead}");

        $deadBacklog = 0;

        if (! $dryRun) {
            $deadBacklog = $this->countDeadLetters($patientId);

            $this->recordDeadLetterAlertIfNeeded(
                deadBacklog: $deadBacklog,
                deadInBatch: $dead,
                scopeMetadata: ['patient_id' => $patientId],
            );
        }

        if ($strictExit && ! $dryRun && ($failed > 0 || $dead > 0 || $deadBacklog > 0)) {
            $this->error(
                "STRICT_EXIT_STATUS: FAIL. EMR sync còn failed={$failed}, dead_in_batch={$dead}, dead_backlog={$deadBacklog}.",
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function countDeadLetters(?int $patientId): int
    {
        return EmrSyncEvent::query()
            ->when($patientId !== null, fn ($query) => $query->where('patient_id', $patientId))
            ->where('status', EmrSyncEvent::STATUS_DEAD)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $scopeMetadata
     */
    protected function recordDeadLetterAlertIfNeeded(int $deadBacklog, int $deadInBatch, array $scopeMetadata = []): void
    {
        if (! ClinicRuntimeSettings::syncDeadLetterAlertEnabled()) {
            return;
        }

        $threshold = ClinicRuntimeSettings::syncDeadLetterAlertThreshold();

        if ($deadBacklog < $threshold) {
            return;
        }

        $runbookCategory = 'emr_dead_letter';
        $runbook = (string) data_get(ClinicRuntimeSettings::opsAlertRunbookMap(), "{$runbookCategory}.runbook", '');

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: array_merge([
                'channel' => 'sync_dead_letter_alert',
                'command' => 'emr:sync-events',
                'runbook_category' => $runbookCategory,
                'runbook' => $runbook,
                'dead_backlog' => $deadBacklog,
                'dead_in_batch' => $deadInBatch,
                'threshold' => $threshold,
            ], $scopeMetadata),
        );

        $this->warn(
            "DEAD_LETTER_ALERT: EMR dead_backlog={$deadBacklog} (threshold={$threshold}).",
        );
    }

    protected function processEvent(int $eventId): string
    {
        $claim = DB::transaction(function () use ($eventId): array {
            $event = EmrSyncEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return [
                    'claimed' => false,
                    'status' => EmrSyncEvent::STATUS_FAILED,
                ];
            }

            if (! in_array($event->status, [EmrSyncEvent::STATUS_PENDING, EmrSyncEvent::STATUS_FAILED], true)) {
                return [
                    'claimed' => false,
                    'status' => (string) $event->status,
                ];
            }

            if ($event->next_retry_at && $event->next_retry_at->isFuture()) {
                return [
                    'claimed' => false,
                    'status' => (string) $event->status,
                ];
            }

            $event->markProcessing();

            return [
                'claimed' => true,
                'event_id' => (int) $event->id,
            ];
        }, 3);

        if (($claim['claimed'] ?? false) !== true) {
            return (string) ($claim['status'] ?? EmrSyncEvent::STATUS_FAILED);
        }

        $processingEvent = EmrSyncEvent::query()->find((int) $claim['event_id']);

        if (! $processingEvent) {
            return EmrSyncEvent::STATUS_FAILED;
        }

        $integrationResult = $this->emrIntegrationService->pushPatientPayload($processingEvent);
        $isSuccess = (bool) ($integrationResult['success'] ?? false);

        return DB::transaction(function () use ($eventId, $integrationResult, $isSuccess): string {
            $event = EmrSyncEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return EmrSyncEvent::STATUS_FAILED;
            }

            if ($event->status !== EmrSyncEvent::STATUS_PROCESSING) {
                return (string) $event->status;
            }

            $httpStatus = isset($integrationResult['status']) ? (int) $integrationResult['status'] : null;
            $responsePayload = is_array($integrationResult['response'] ?? null)
                ? $integrationResult['response']
                : null;

            EmrSyncLog::query()->create([
                'emr_sync_event_id' => $event->id,
                'attempt' => (int) $event->attempts,
                'status' => $isSuccess ? EmrSyncEvent::STATUS_SYNCED : EmrSyncEvent::STATUS_FAILED,
                'http_status' => $httpStatus,
                'request_payload' => $event->payload,
                'response_payload' => $responsePayload,
                'error_message' => $isSuccess ? null : (string) ($integrationResult['message'] ?? 'Sync failed'),
                'attempted_at' => now(),
            ]);

            if ($isSuccess) {
                $externalPatientId = $integrationResult['external_patient_id'] ?? null;
                $externalPatientId = filled($externalPatientId)
                    ? (string) $externalPatientId
                    : (string) $event->patient_id;

                $event->markSynced($externalPatientId, $httpStatus);

                EmrPatientMap::query()->updateOrCreate(
                    ['patient_id' => $event->patient_id],
                    [
                        'emr_patient_id' => $externalPatientId,
                        'payload_checksum' => $event->payload_checksum,
                        'last_event_id' => $event->id,
                        'last_synced_at' => now(),
                        'sync_meta' => [
                            'event_key' => $event->event_key,
                            'event_type' => $event->event_type,
                            'http_status' => $httpStatus,
                            'provider' => ClinicRuntimeSettings::emrProvider(),
                        ],
                    ],
                );

                AuditLog::record(
                    entityType: AuditLog::ENTITY_AUTOMATION,
                    entityId: $event->id,
                    action: AuditLog::ACTION_SYNC,
                    actorId: auth()->id(),
                    metadata: [
                        'command' => 'emr:sync-events',
                        'event_id' => $event->id,
                        'patient_id' => $event->patient_id,
                        'event_type' => $event->event_type,
                        'status' => EmrSyncEvent::STATUS_SYNCED,
                        'http_status' => $httpStatus,
                    ],
                );

                $this->emrAuditLogger->recordSyncEvent(
                    event: $event,
                    action: EmrAuditLog::ACTION_SYNC,
                    actorId: auth()->id(),
                    context: [
                        'command' => 'emr:sync-events',
                        'event_type' => $event->event_type,
                        'status' => EmrSyncEvent::STATUS_SYNCED,
                        'http_status' => $httpStatus,
                        'external_patient_id' => $externalPatientId,
                    ],
                );

                return EmrSyncEvent::STATUS_SYNCED;
            }

            $event->markFailure(
                $httpStatus,
                (string) ($integrationResult['message'] ?? 'Sync failed'),
            );

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: $event->id,
                action: AuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'emr:sync-events',
                    'event_id' => $event->id,
                    'patient_id' => $event->patient_id,
                    'event_type' => $event->event_type,
                    'status' => $event->fresh()?->status,
                    'http_status' => $httpStatus,
                    'message' => $integrationResult['message'] ?? null,
                ],
            );

            $this->emrAuditLogger->recordSyncEvent(
                event: $event,
                action: EmrAuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                context: [
                    'command' => 'emr:sync-events',
                    'event_type' => $event->event_type,
                    'status' => $event->fresh()?->status,
                    'http_status' => $httpStatus,
                    'message' => $integrationResult['message'] ?? null,
                ],
            );

            return (string) ($event->fresh()?->status ?? EmrSyncEvent::STATUS_FAILED);
        }, 3);
    }
}
