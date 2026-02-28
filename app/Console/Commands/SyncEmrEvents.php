<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmrPatientMap;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Services\EmrIntegrationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEmrEvents extends Command
{
    protected $signature = 'emr:sync-events {--limit=100 : Số lượng event tối đa mỗi lần chạy} {--patient_id= : Chỉ sync cho một bệnh nhân} {--dry-run : Chỉ preview, không ghi dữ liệu}';

    protected $description = 'Đồng bộ outbox EMR (CRM -> EMR) theo cơ chế retry + dead-letter.';

    public function __construct(
        protected EmrIntegrationService $emrIntegrationService,
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

        return self::SUCCESS;
    }

    protected function processEvent(int $eventId): string
    {
        return DB::transaction(function () use ($eventId): string {
            $event = EmrSyncEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return EmrSyncEvent::STATUS_FAILED;
            }

            if (! in_array($event->status, [EmrSyncEvent::STATUS_PENDING, EmrSyncEvent::STATUS_FAILED], true)) {
                return (string) $event->status;
            }

            if ($event->next_retry_at && $event->next_retry_at->isFuture()) {
                return (string) $event->status;
            }

            $event->markProcessing();

            $integrationResult = $this->emrIntegrationService->pushPatientPayload($event);
            $isSuccess = (bool) ($integrationResult['success'] ?? false);
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

            return (string) ($event->fresh()?->status ?? EmrSyncEvent::STATUS_FAILED);
        });
    }
}
