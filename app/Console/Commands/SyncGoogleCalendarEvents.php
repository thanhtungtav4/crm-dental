<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\GoogleCalendarEventMap;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Services\GoogleCalendarIntegrationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncGoogleCalendarEvents extends Command
{
    protected $signature = 'google-calendar:sync-events
        {--limit=100 : Số lượng event tối đa mỗi lần chạy}
        {--appointment_id= : Chỉ sync cho một lịch hẹn}
        {--dry-run : Chỉ preview, không ghi dữ liệu}';

    protected $description = 'Đồng bộ outbox Google Calendar (CRM -> Google) theo cơ chế retry + dead-letter.';

    public function __construct(
        protected GoogleCalendarIntegrationService $googleCalendarIntegrationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation Google Calendar sync.',
        );

        if (! ClinicRuntimeSettings::isGoogleCalendarEnabled()) {
            $this->warn('Google Calendar integration đang tắt. Không có dữ liệu cần sync.');

            return self::SUCCESS;
        }

        if (! in_array(ClinicRuntimeSettings::googleCalendarSyncMode(), ['two_way', 'one_way_to_google'], true)) {
            $this->warn('Google Calendar sync mode không cho phép đẩy CRM -> Google. Bỏ qua.');

            return self::SUCCESS;
        }

        if (! $this->googleCalendarIntegrationService->isConfigured()) {
            $this->error('Google Calendar chưa cấu hình đầy đủ (client_id/client_secret/refresh_token/calendar_id).');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $appointmentId = $this->option('appointment_id') ? (int) $this->option('appointment_id') : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = GoogleCalendarSyncEvent::query()
            ->ready()
            ->orderBy('id')
            ->limit($limit);

        if ($appointmentId) {
            $query->where('appointment_id', $appointmentId);
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->info('Không có event Google Calendar nào sẵn sàng để xử lý.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;
        $dead = 0;

        foreach ($events as $event) {
            if ($dryRun) {
                $this->line("DRY RUN - would sync Google event #{$event->id} ({$event->event_type}) for appointment {$event->appointment_id}");

                continue;
            }

            $result = $this->processEvent((int) $event->id);

            if ($result === GoogleCalendarSyncEvent::STATUS_SYNCED) {
                $synced++;
            } elseif ($result === GoogleCalendarSyncEvent::STATUS_DEAD) {
                $dead++;
            } else {
                $failed++;
            }
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Google Calendar sync processed. synced={$synced}, failed={$failed}, dead={$dead}");

        return self::SUCCESS;
    }

    protected function processEvent(int $eventId): string
    {
        return DB::transaction(function () use ($eventId): string {
            $event = GoogleCalendarSyncEvent::query()
                ->lockForUpdate()
                ->find($eventId);

            if (! $event) {
                return GoogleCalendarSyncEvent::STATUS_FAILED;
            }

            if (! in_array($event->status, [GoogleCalendarSyncEvent::STATUS_PENDING, GoogleCalendarSyncEvent::STATUS_FAILED], true)) {
                return (string) $event->status;
            }

            if ($event->next_retry_at && $event->next_retry_at->isFuture()) {
                return (string) $event->status;
            }

            $event->markProcessing();

            $eventMap = GoogleCalendarEventMap::query()
                ->where('appointment_id', $event->appointment_id)
                ->first();

            if ($event->event_type === GoogleCalendarSyncEvent::EVENT_DELETE) {
                $integrationResult = $this->googleCalendarIntegrationService->deleteEvent((string) ($eventMap?->google_event_id ?? ''));
            } else {
                $integrationResult = $this->googleCalendarIntegrationService->upsertEvent(
                    googleEventId: $eventMap?->google_event_id,
                    payload: is_array($event->payload) ? $event->payload : [],
                );
            }

            $isSuccess = (bool) ($integrationResult['success'] ?? false);
            $httpStatus = isset($integrationResult['status']) ? (int) $integrationResult['status'] : null;
            $responsePayload = is_array($integrationResult['response'] ?? null)
                ? $integrationResult['response']
                : null;

            GoogleCalendarSyncLog::query()->create([
                'google_calendar_sync_event_id' => $event->id,
                'attempt' => (int) $event->attempts,
                'status' => $isSuccess ? GoogleCalendarSyncEvent::STATUS_SYNCED : GoogleCalendarSyncEvent::STATUS_FAILED,
                'http_status' => $httpStatus,
                'request_payload' => is_array($event->payload) ? $event->payload : null,
                'response_payload' => $responsePayload,
                'error_message' => $isSuccess ? null : (string) ($integrationResult['message'] ?? 'Sync failed'),
                'attempted_at' => now(),
            ]);

            if ($isSuccess) {
                $googleEventId = filled($integrationResult['google_event_id'] ?? null)
                    ? (string) $integrationResult['google_event_id']
                    : $eventMap?->google_event_id;

                if ($event->event_type === GoogleCalendarSyncEvent::EVENT_DELETE) {
                    $eventMap?->delete();
                } else {
                    GoogleCalendarEventMap::query()->updateOrCreate(
                        ['appointment_id' => $event->appointment_id],
                        [
                            'branch_id' => $event->branch_id,
                            'calendar_id' => ClinicRuntimeSettings::googleCalendarCalendarId(),
                            'google_event_id' => (string) $googleEventId,
                            'payload_checksum' => $event->payload_checksum,
                            'last_event_id' => $event->id,
                            'external_updated_at' => filled($integrationResult['updated'] ?? null)
                                ? Carbon::parse((string) $integrationResult['updated'])
                                : null,
                            'last_synced_at' => now(),
                            'sync_meta' => [
                                'event_key' => $event->event_key,
                                'event_type' => $event->event_type,
                                'http_status' => $httpStatus,
                            ],
                        ],
                    );
                }

                $event->markSynced($googleEventId, $httpStatus);

                AuditLog::record(
                    entityType: AuditLog::ENTITY_AUTOMATION,
                    entityId: $event->id,
                    action: AuditLog::ACTION_SYNC,
                    actorId: auth()->id(),
                    metadata: [
                        'command' => 'google-calendar:sync-events',
                        'event_id' => $event->id,
                        'appointment_id' => $event->appointment_id,
                        'event_type' => $event->event_type,
                        'status' => GoogleCalendarSyncEvent::STATUS_SYNCED,
                        'http_status' => $httpStatus,
                    ],
                );

                return GoogleCalendarSyncEvent::STATUS_SYNCED;
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
                    'command' => 'google-calendar:sync-events',
                    'event_id' => $event->id,
                    'appointment_id' => $event->appointment_id,
                    'event_type' => $event->event_type,
                    'status' => $event->fresh()?->status,
                    'http_status' => $httpStatus,
                    'message' => $integrationResult['message'] ?? null,
                ],
            );

            return (string) ($event->fresh()?->status ?? GoogleCalendarSyncEvent::STATUS_FAILED);
        });
    }
}
