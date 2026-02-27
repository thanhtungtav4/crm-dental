<?php

namespace App\Console\Commands;

use App\Services\ActionPermissionBaselineService;
use Illuminate\Console\Command;

class AssertActionPermissionBaseline extends Command
{
    protected $signature = 'security:assert-action-permission-baseline {--sync : Đồng bộ baseline trước khi assert}';

    protected $description = 'Kiểm tra baseline Action:* permissions và role matrix theo SensitiveActionRegistry.';

    public function __construct(
        protected ActionPermissionBaselineService $baselineService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ((bool) $this->option('sync')) {
            $summary = $this->baselineService->sync();
            $this->line(
                'Synced baseline: '
                .'roles='.$summary['created_roles']
                .', permissions='.$summary['upserted_permissions']
                .', granted='.$summary['granted']
                .', revoked='.$summary['revoked'],
            );
        }

        $report = $this->baselineService->report();

        if ($report['ok']) {
            $this->info('Action permission baseline: OK.');

            return self::SUCCESS;
        }

        foreach ($report['missing_permissions'] as $permission) {
            $this->error('Missing permission: '.$permission);
        }

        foreach ($report['missing_roles'] as $role) {
            $this->error('Missing role: '.$role);
        }

        foreach ($report['matrix_mismatches'] as $mismatch) {
            $this->error(
                'Role matrix mismatch: permission='.$mismatch['permission']
                .', role='.$mismatch['role']
                .', expected='.(int) $mismatch['expected']
                .', actual='.(int) $mismatch['actual'],
            );
        }

        return self::FAILURE;
    }
}
