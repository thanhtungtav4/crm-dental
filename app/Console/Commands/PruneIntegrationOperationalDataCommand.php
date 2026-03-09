<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneIntegrationOperationalDataCommand extends Command
{
    protected $signature = 'integrations:prune-operational-data
        {--days= : Override retention days cho tat ca bang operational integration}
        {--dry-run : Chi thong ke, khong xoa du lieu}
        {--strict : Tra exit code loi neu command gap exception}';

    protected $description = 'Dọn dữ liệu vận hành integration quá hạn retention để giảm footprint PII/PHI.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy retention cho dữ liệu integration.',
        );

        $retentionDays = $this->resolveRetentionDays();
        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');

        try {
            $summary = [
                'dry_run' => $dryRun,
                'retention_days' => $retentionDays,
                'web_lead' => $this->pruneWebLeadIngestions($retentionDays['web_lead'], $dryRun),
                'web_lead_email' => $this->pruneWebLeadEmailDeliveries($retentionDays['web_lead'], $dryRun),
                'zalo_webhook' => $this->pruneZaloWebhookEvents($retentionDays['zalo_webhook'], $dryRun),
                'emr' => $this->pruneEmrSyncData($retentionDays['emr'], $dryRun),
                'google_calendar' => $this->pruneGoogleCalendarSyncData($retentionDays['google_calendar'], $dryRun),
            ];

            $this->line(sprintf(
                'dry_run=%s web_lead=%d web_lead_email=%d zalo_webhook=%d emr_logs=%d emr_events=%d google_logs=%d google_events=%d',
                $dryRun ? 'yes' : 'no',
                (int) data_get($summary, 'web_lead.total', 0),
                (int) data_get($summary, 'web_lead_email.total', 0),
                (int) data_get($summary, 'zalo_webhook.total', 0),
                (int) data_get($summary, 'emr.logs', 0),
                (int) data_get($summary, 'emr.events', 0),
                (int) data_get($summary, 'google_calendar.logs', 0),
                (int) data_get($summary, 'google_calendar.events', 0),
            ));

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'integrations:prune-operational-data',
                    'summary' => $summary,
                ],
            );

            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'integrations:prune-operational-data',
                    'retention_days' => $retentionDays,
                    'dry_run' => $dryRun,
                    'error' => $throwable->getMessage(),
                ],
            );

            $this->error('Không thể prune dữ liệu vận hành integration: '.$throwable->getMessage());

            return $strict ? self::FAILURE : self::SUCCESS;
        }
    }

    /**
     * @return array{web_lead:int,zalo_webhook:int,emr:int,google_calendar:int}
     */
    protected function resolveRetentionDays(): array
    {
        $override = $this->option('days');

        if ($override !== null && $override !== '') {
            if (! is_numeric($override)) {
                return [
                    'web_lead' => ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
                    'zalo_webhook' => ClinicRuntimeSettings::zaloWebhookRetentionDays(),
                    'emr' => ClinicRuntimeSettings::emrOperationalRetentionDays(),
                    'google_calendar' => ClinicRuntimeSettings::googleCalendarOperationalRetentionDays(),
                ];
            }

            $days = max(1, (int) $override);

            return [
                'web_lead' => $days,
                'zalo_webhook' => $days,
                'emr' => $days,
                'google_calendar' => $days,
            ];
        }

        return [
            'web_lead' => ClinicRuntimeSettings::webLeadOperationalRetentionDays(),
            'zalo_webhook' => ClinicRuntimeSettings::zaloWebhookRetentionDays(),
            'emr' => ClinicRuntimeSettings::emrOperationalRetentionDays(),
            'google_calendar' => ClinicRuntimeSettings::googleCalendarOperationalRetentionDays(),
        ];
    }

    /**
     * @return array{retention_days:int,cutoff:string,total:int,deleted?:int}
     */
    protected function pruneWebLeadIngestions(int $retentionDays, bool $dryRun): array
    {
        $cutoff = now()->subDays($retentionDays);
        $query = WebLeadIngestion::query()
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

        $total = (clone $query)->count();

        $summary = [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'total' => $total,
        ];

        if (! $dryRun) {
            $summary['deleted'] = $query->delete();
        }

        return $summary;
    }

    /**
     * @return array{retention_days:int,cutoff:string,total:int,deleted?:int}
     */
    protected function pruneWebLeadEmailDeliveries(int $retentionDays, bool $dryRun): array
    {
        $cutoff = now()->subDays($retentionDays);
        $query = WebLeadEmailDelivery::query()
            ->whereIn('status', [
                WebLeadEmailDelivery::STATUS_SENT,
                WebLeadEmailDelivery::STATUS_DEAD,
                WebLeadEmailDelivery::STATUS_SKIPPED,
            ])
            ->where('updated_at', '<', $cutoff);

        $summary = [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'total' => (clone $query)->count(),
        ];

        if (! $dryRun) {
            $summary['deleted'] = $query->delete();
        }

        return $summary;
    }

    /**
     * @return array{retention_days:int,cutoff:string,total:int,deleted?:int}
     */
    protected function pruneZaloWebhookEvents(int $retentionDays, bool $dryRun): array
    {
        $cutoff = now()->subDays($retentionDays);
        $query = ZaloWebhookEvent::query()
            ->where(function (Builder $builder) use ($cutoff): void {
                $builder
                    ->where('received_at', '<', $cutoff)
                    ->orWhere(function (Builder $fallbackQuery) use ($cutoff): void {
                        $fallbackQuery
                            ->whereNull('received_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });

        $total = (clone $query)->count();

        $summary = [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'total' => $total,
        ];

        if (! $dryRun) {
            $summary['deleted'] = $query->delete();
        }

        return $summary;
    }

    /**
     * @return array{retention_days:int,cutoff:string,logs:int,events:int,logs_deleted?:int,events_deleted?:int}
     */
    protected function pruneEmrSyncData(int $retentionDays, bool $dryRun): array
    {
        $cutoff = now()->subDays($retentionDays);
        $logQuery = EmrSyncLog::query()
            ->where('attempted_at', '<', $cutoff);

        $eventQuery = EmrSyncEvent::query()
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

        $summary = [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'logs' => (clone $logQuery)->count(),
            'events' => (clone $eventQuery)->count(),
        ];

        if (! $dryRun) {
            $summary['logs_deleted'] = $logQuery->delete();
            $summary['events_deleted'] = $eventQuery->delete();
        }

        return $summary;
    }

    /**
     * @return array{retention_days:int,cutoff:string,logs:int,events:int,logs_deleted?:int,events_deleted?:int}
     */
    protected function pruneGoogleCalendarSyncData(int $retentionDays, bool $dryRun): array
    {
        $cutoff = now()->subDays($retentionDays);
        $logQuery = GoogleCalendarSyncLog::query()
            ->where('attempted_at', '<', $cutoff);

        $eventQuery = GoogleCalendarSyncEvent::query()
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

        $summary = [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toDateTimeString(),
            'logs' => (clone $logQuery)->count(),
            'events' => (clone $eventQuery)->count(),
        ];

        if (! $dryRun) {
            $summary['logs_deleted'] = $logQuery->delete();
            $summary['events_deleted'] = $eventQuery->delete();
        }

        return $summary;
    }
}
