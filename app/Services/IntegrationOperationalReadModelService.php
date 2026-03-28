<?php

namespace App\Services;

use App\Models\EmrSyncEvent;
use App\Models\EmrSyncLog;
use App\Models\GoogleCalendarSyncEvent;
use App\Models\GoogleCalendarSyncLog;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use App\Models\ZaloWebhookEvent;
use Illuminate\Database\Eloquent\Builder;

class IntegrationOperationalReadModelService
{
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

    public function emrRetentionCandidateCount(int $retentionDays): int
    {
        $logs = $this->emrLogRetentionQuery($retentionDays)->count();
        $events = $this->emrEventRetentionQuery($retentionDays)->count();

        return $logs + $events;
    }

    public function emrDeadBacklogCount(): int
    {
        return EmrSyncEvent::query()
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

    public function googleCalendarDeadBacklogCount(): int
    {
        return GoogleCalendarSyncEvent::query()
            ->where('status', GoogleCalendarSyncEvent::STATUS_DEAD)
            ->count();
    }

    public function googleCalendarFailedBacklogCount(): int
    {
        return GoogleCalendarSyncEvent::query()
            ->where('status', GoogleCalendarSyncEvent::STATUS_FAILED)
            ->count();
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
}
