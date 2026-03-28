<?php

namespace App\Console\Commands;

use App\Services\IntegrationOperationalReadModelService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PrunePopupAnnouncementLogs extends Command
{
    protected $signature = 'popups:prune {--days= : Số ngày retention cho log popup}';

    protected $description = 'Dọn log popup announcement theo retention policy runtime';

    public function handle(IntegrationOperationalReadModelService $integrationOperationalReadModelService): int
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

        $deliveriesDeleted = $integrationOperationalReadModelService
            ->popupDeliveryRetentionQuery($retentionDays)
            ->delete();

        $announcementsDeleted = $integrationOperationalReadModelService
            ->popupAnnouncementRetentionQuery($retentionDays)
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
