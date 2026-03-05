<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ZnsAutomationEvent;
use App\Models\ZnsAutomationLog;
use App\Services\ZnsProviderClient;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncZnsAutomationEvents extends Command
{
    protected $signature = 'zns:sync-automation-events
        {--limit=100 : Số lượng event tối đa mỗi lần chạy}
        {--event_type= : Chỉ xử lý một event_type cụ thể}
        {--dry-run : Chỉ preview, không ghi dữ liệu}
        {--strict-exit : Trả về exit code lỗi nếu vẫn còn failed/dead-letter sau khi chạy}';

    protected $description = 'Xử lý outbox ZNS automation (lead welcome / appointment reminder / birthday) với retry + reclaim.';

    public function __construct(
        protected ZnsProviderClient $znsProviderClient,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation ZNS.',
        );

        if (! ClinicRuntimeSettings::boolean('zns.enabled', false)) {
            $this->warn('ZNS integration đang tắt. Không có event automation cần xử lý.');

            return self::SUCCESS;
        }

        if (trim((string) ClinicRuntimeSettings::get('zns.access_token', '')) === '') {
            $this->error('Thiếu ZNS access token. Không thể xử lý event automation.');

            return self::FAILURE;
        }

        if (ClinicRuntimeSettings::znsSendEndpoint() === '') {
            $this->error('Thiếu ZNS send endpoint. Không thể xử lý event automation.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $eventType = trim((string) $this->option('event_type'));
        $dryRun = (bool) $this->option('dry-run');
        $strictExit = (bool) $this->option('strict-exit');

        if (! $dryRun) {
            $reclaimed = ZnsAutomationEvent::reclaimStaleProcessing();
            if ($reclaimed > 0) {
                $this->warn("Đã reclaim {$reclaimed} event ZNS automation bị kẹt trạng thái processing.");
            }
        }

        $query = ZnsAutomationEvent::query()
            ->ready()
            ->orderBy('id')
            ->limit($limit);

        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('Không có event ZNS automation nào sẵn sàng để xử lý.');

            if (! $dryRun) {
                $deadBacklog = $this->countDeadLetters($eventType);
                $this->recordDeadLetterAlertIfNeeded(
                    deadBacklog: $deadBacklog,
                    deadInBatch: 0,
                    scopeMetadata: ['event_type_filter' => $eventType !== '' ? $eventType : null],
                );

                if ($strictExit && $deadBacklog > 0) {
                    $this->error("STRICT_EXIT_STATUS: FAIL. ZNS automation outbox còn {$deadBacklog} dead-letter event.");

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        $dead = 0;

        foreach ($events as $event) {
            if ($dryRun) {
                $this->line("DRY RUN - would process ZNS event #{$event->id} ({$event->event_type})");

                continue;
            }

            $result = $this->processEvent((int) $event->id);

            if ($result === ZnsAutomationEvent::STATUS_SENT) {
                $sent++;
            } elseif ($result === ZnsAutomationEvent::STATUS_DEAD) {
                $dead++;
            } else {
                $failed++;
            }
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] ZNS automation sync processed. sent={$sent}, failed={$failed}, dead={$dead}");

        $deadBacklog = 0;

        if (! $dryRun) {
            $deadBacklog = $this->countDeadLetters($eventType);

            $this->recordDeadLetterAlertIfNeeded(
                deadBacklog: $deadBacklog,
                deadInBatch: $dead,
                scopeMetadata: ['event_type_filter' => $eventType !== '' ? $eventType : null],
            );
        }

        if ($strictExit && ! $dryRun && ($failed > 0 || $dead > 0 || $deadBacklog > 0)) {
            $this->error(
                "STRICT_EXIT_STATUS: FAIL. ZNS automation sync còn failed={$failed}, dead_in_batch={$dead}, dead_backlog={$deadBacklog}.",
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function countDeadLetters(string $eventType): int
    {
        return ZnsAutomationEvent::query()
            ->when($eventType !== '', fn ($query) => $query->where('event_type', $eventType))
            ->where('status', ZnsAutomationEvent::STATUS_DEAD)
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

        $runbookCategory = 'zns_automation_dead_letter';
        $runbook = (string) data_get(ClinicRuntimeSettings::opsAlertRunbookMap(), "{$runbookCategory}.runbook", '');

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: array_merge([
                'channel' => 'sync_dead_letter_alert',
                'command' => 'zns:sync-automation-events',
                'runbook_category' => $runbookCategory,
                'runbook' => $runbook,
                'dead_backlog' => $deadBacklog,
                'dead_in_batch' => $deadInBatch,
                'threshold' => $threshold,
            ], $scopeMetadata),
        );

        $this->warn(
            "DEAD_LETTER_ALERT: ZNS automation dead_backlog={$deadBacklog} (threshold={$threshold}).",
        );
    }

    protected function processEvent(int $eventId): string
    {
        $claim = DB::transaction(function () use ($eventId): array {
            $event = ZnsAutomationEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return [
                    'claimed' => false,
                    'status' => ZnsAutomationEvent::STATUS_FAILED,
                ];
            }

            if (! in_array($event->status, [ZnsAutomationEvent::STATUS_PENDING, ZnsAutomationEvent::STATUS_FAILED], true)) {
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
                'event_key' => (string) $event->event_key,
                'event_type' => (string) $event->event_type,
                'phone' => trim((string) ($event->normalized_phone ?: $event->phone ?: '')),
                'template_id' => trim((string) $event->template_id_snapshot),
                'payload' => is_array($event->payload) ? $event->payload : [],
            ];
        }, 3);

        if (($claim['claimed'] ?? false) !== true) {
            return (string) ($claim['status'] ?? ZnsAutomationEvent::STATUS_FAILED);
        }

        $providerPayload = null;
        $retryable = true;
        $sendResult = [
            'success' => false,
            'status' => null,
            'provider_message_id' => null,
            'provider_status_code' => null,
            'error' => null,
            'response' => null,
        ];

        if (($claim['template_id'] ?? '') === '') {
            $retryable = false;
            $sendResult['provider_status_code'] = 'validation_missing_template';
            $sendResult['error'] = 'Thiếu template_id_snapshot cho event ZNS.';
        } elseif (($claim['phone'] ?? '') === '') {
            $retryable = false;
            $sendResult['provider_status_code'] = 'validation_missing_phone';
            $sendResult['error'] = 'Thiếu số điện thoại nhận ZNS.';
        } else {
            $providerPayload = [
                'phone' => (string) $claim['phone'],
                'template_id' => (string) $claim['template_id'],
                'template_data' => is_array($claim['payload'] ?? null) ? $claim['payload'] : [],
                'tracking_id' => (string) $claim['event_key'],
                'campaign_code' => 'auto-'.(string) ($claim['event_type'] ?? 'event'),
            ];

            $sendResult = $this->znsProviderClient->sendTemplate($providerPayload);
            $statusCode = isset($sendResult['status']) ? (int) $sendResult['status'] : null;

            if ($statusCode !== null && $statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                $retryable = false;
            }
        }

        $isSuccess = (bool) ($sendResult['success'] ?? false);
        $httpStatus = isset($sendResult['status']) ? (int) $sendResult['status'] : null;
        $responsePayload = is_array($sendResult['response'] ?? null)
            ? $sendResult['response']
            : null;

        return DB::transaction(function () use (
            $eventId,
            $providerPayload,
            $sendResult,
            $isSuccess,
            $httpStatus,
            $responsePayload,
            $retryable,
        ): string {
            $event = ZnsAutomationEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return ZnsAutomationEvent::STATUS_FAILED;
            }

            if ($event->status !== ZnsAutomationEvent::STATUS_PROCESSING) {
                return (string) $event->status;
            }

            ZnsAutomationLog::query()->create([
                'zns_automation_event_id' => $event->id,
                'attempt' => (int) $event->attempts,
                'status' => $isSuccess ? ZnsAutomationEvent::STATUS_SENT : ZnsAutomationEvent::STATUS_FAILED,
                'http_status' => $httpStatus,
                'request_payload' => $providerPayload,
                'response_payload' => $responsePayload,
                'error_message' => $isSuccess ? null : (string) ($sendResult['error'] ?? 'ZNS provider request failed.'),
                'attempted_at' => now(),
            ]);

            if ($isSuccess) {
                $event->markSent(
                    providerMessageId: $sendResult['provider_message_id'] ?? null,
                    providerStatusCode: $sendResult['provider_status_code'] ?? null,
                    httpStatus: $httpStatus,
                    providerResponse: $responsePayload,
                );

                return ZnsAutomationEvent::STATUS_SENT;
            }

            $event->markFailure(
                httpStatus: $httpStatus,
                message: (string) ($sendResult['error'] ?? 'ZNS provider request failed.'),
                retryable: $retryable,
                providerStatusCode: $sendResult['provider_status_code'] ?? null,
                providerResponse: $responsePayload,
            );

            return (string) ($event->fresh()?->status ?? ZnsAutomationEvent::STATUS_FAILED);
        }, 3);
    }
}
