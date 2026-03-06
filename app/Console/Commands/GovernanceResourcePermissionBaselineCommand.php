<?php

namespace App\Console\Commands;

use App\Services\GovernanceResourcePermissionBaselineService;
use Illuminate\Console\Command;

class GovernanceResourcePermissionBaselineCommand extends Command
{
    protected $signature = 'security:assert-governance-resource-baseline {--sync : Dong bo baseline truoc khi assert}';

    protected $description = 'Kiem tra baseline permission cho resource governance nhay cam (Branch/User/Role).';

    public function __construct(
        protected GovernanceResourcePermissionBaselineService $baselineService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ((bool) $this->option('sync')) {
            $summary = $this->baselineService->sync();
            $this->line(
                'Synced governance baseline: '
                .'roles='.$summary['created_roles']
                .', permissions='.$summary['upserted_permissions']
                .', granted='.$summary['granted']
                .', revoked='.$summary['revoked'],
            );
        }

        $report = $this->baselineService->report();

        if ($report['ok']) {
            $this->info('Governance resource permission baseline: OK.');

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
