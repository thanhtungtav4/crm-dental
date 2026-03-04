<?php

namespace App\Console\Commands;

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PrunePopupAnnouncementLogs extends Command
{
    protected $signature = 'popups:prune {--days= : Số ngày retention cho log popup}';

    protected $description = 'Dọn log popup announcement theo retention policy runtime';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $retentionDays = is_numeric($daysOption)
            ? (int) $daysOption
            : ClinicRuntimeSettings::popupAnnouncementRetentionDays();

        if ($retentionDays <= 0) {
            $this->warn('Retention days <= 0, bỏ qua dọn log popup.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);

        $deliveriesDeleted = PopupAnnouncementDelivery::query()
            ->whereIn('status', [
                PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED,
                PopupAnnouncementDelivery::STATUS_DISMISSED,
                PopupAnnouncementDelivery::STATUS_EXPIRED,
            ])
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('acknowledged_at', '<', $cutoff)
                    ->orWhere('dismissed_at', '<', $cutoff)
                    ->orWhere('expired_at', '<', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested
                            ->whereNull('acknowledged_at')
                            ->whereNull('dismissed_at')
                            ->whereNull('expired_at')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->delete();

        $announcementsDeleted = PopupAnnouncement::query()
            ->whereIn('status', [
                PopupAnnouncement::STATUS_CANCELLED,
                PopupAnnouncement::STATUS_EXPIRED,
            ])
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('deliveries')
            ->delete();

        $this->line(sprintf(
            'retention_days=%d cutoff=%s deliveries_deleted=%d announcements_deleted=%d',
            $retentionDays,
            $this->formatCutoff($cutoff),
            $deliveriesDeleted,
            $announcementsDeleted,
        ));

        return self::SUCCESS;
    }

    protected function formatCutoff(Carbon $cutoff): string
    {
        return $cutoff->format('Y-m-d H:i:s');
    }
}
