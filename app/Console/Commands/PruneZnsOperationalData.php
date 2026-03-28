<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\ZnsOperationalReadModelService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;

class PruneZnsOperationalData extends Command
{
    protected $signature = 'zns:prune-operational-data
        {--days= : Số ngày retention override cho dữ liệu vận hành ZNS}
        {--dry-run : Chỉ thống kê, không xóa}
        {--strict : Trả exit code lỗi nếu có lỗi khi dọn dữ liệu}';

    protected $description = 'Dọn dữ liệu vận hành ZNS quá hạn retention để giảm PII footprint.';

    public function __construct(protected ZnsOperationalReadModelService $znsOperationalReadModelService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy retention ZNS.',
        );

        $retentionDays = $this->resolveRetentionDays();
        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');

        if ($retentionDays <= 0) {
            $this->warn('Retention days <= 0, bỏ qua dọn dữ liệu ZNS.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);

        try {
            $logQuery = $this->znsOperationalReadModelService->automationLogRetentionQuery($retentionDays);
            $eventQuery = $this->znsOperationalReadModelService->automationRetentionQuery($retentionDays);
            $deliveryQuery = $this->znsOperationalReadModelService->deliveryRetentionQuery($retentionDays);

            $summary = [
                'retention_days' => $retentionDays,
                'cutoff' => $cutoff->toDateTimeString(),
                'dry_run' => $dryRun,
                'logs' => (clone $logQuery)->count(),
                'events' => (clone $eventQuery)->count(),
                'deliveries' => (clone $deliveryQuery)->count(),
            ];

            if (! $dryRun) {
                $summary['logs_deleted'] = $logQuery->delete();
                $summary['events_deleted'] = $eventQuery->delete();
                $summary['deliveries_deleted'] = $deliveryQuery->delete();
            }

            $this->line(sprintf(
                'retention_days=%d cutoff=%s logs=%d events=%d deliveries=%d dry_run=%s',
                $retentionDays,
                $summary['cutoff'],
                (int) $summary['logs'],
                (int) $summary['events'],
                (int) $summary['deliveries'],
                $dryRun ? 'yes' : 'no',
            ));

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'zns:prune-operational-data',
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
                    'command' => 'zns:prune-operational-data',
                    'retention_days' => $retentionDays,
                    'cutoff' => $cutoff->toDateTimeString(),
                    'dry_run' => $dryRun,
                    'error' => $throwable->getMessage(),
                ],
            );

            $this->error('Không thể prune dữ liệu vận hành ZNS: '.$throwable->getMessage());

            return $strict ? self::FAILURE : self::SUCCESS;
        }
    }

    protected function resolveRetentionDays(): int
    {
        $option = $this->option('days');

        if ($option !== null && $option !== '') {
            if (! is_numeric($option)) {
                return ClinicRuntimeSettings::znsOperationalRetentionDays();
            }

            return max(0, (int) $option);
        }

        return ClinicRuntimeSettings::znsOperationalRetentionDays();
    }
}
