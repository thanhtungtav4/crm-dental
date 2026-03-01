<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class CheckAlertRunbookMap extends Command
{
    protected $signature = 'ops:check-alert-runbook-map
        {--strict : Fail command neu map category-owner-threshold khong hop le}';

    protected $description = 'Kiem tra map runbook alert theo category-owner-threshold cho production ops.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền kiểm tra runbook alert map.',
        );

        $requiredCategories = [
            'backup_health',
            'restore_drill',
            'scheduler_runtime',
            'kpi_snapshot_sla',
            'security_login_lockout',
        ];
        $runbookMap = ClinicRuntimeSettings::opsAlertRunbookMap();
        $errors = [];

        foreach ($requiredCategories as $category) {
            $item = $runbookMap[$category] ?? null;

            if (! is_array($item)) {
                $errors[] = "missing:{$category}";

                continue;
            }

            $ownerRole = trim((string) ($item['owner_role'] ?? ''));
            $threshold = trim((string) ($item['threshold'] ?? ''));
            $runbook = trim((string) ($item['runbook'] ?? ''));

            if ($ownerRole === '') {
                $errors[] = "owner_role_empty:{$category}";
            } elseif (! Role::query()->where('name', $ownerRole)->exists()) {
                $errors[] = "owner_role_missing:{$category}:{$ownerRole}";
            }

            if ($threshold === '') {
                $errors[] = "threshold_empty:{$category}";
            }

            if ($runbook === '') {
                $errors[] = "runbook_empty:{$category}";
            }
        }

        $this->line('RUNBOOK_MAP_CATEGORY_COUNT: '.count($runbookMap));
        $this->line('RUNBOOK_MAP_ERROR_COUNT: '.count($errors));

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->warn('RUNBOOK_MAP_ERROR: '.$error);
            }
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $errors === [] ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: [
                'command' => 'ops:check-alert-runbook-map',
                'error_count' => count($errors),
                'errors' => $errors,
                'required_categories' => $requiredCategories,
            ],
        );

        if ((bool) $this->option('strict') && $errors !== []) {
            $this->error('Strict mode: alert runbook map chưa hợp lệ.');

            return self::FAILURE;
        }

        $this->info('Alert runbook map check completed.');

        return self::SUCCESS;
    }
}
