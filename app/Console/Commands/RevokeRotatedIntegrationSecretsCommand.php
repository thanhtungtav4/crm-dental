<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\IntegrationSecretRotationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;

class RevokeRotatedIntegrationSecretsCommand extends Command
{
    protected $signature = 'integrations:revoke-rotated-secrets
        {--dry-run : Chi thong ke grace token da het han, khong thu hoi}
        {--strict : Tra exit code loi neu command gap exception}';

    protected $description = 'Thu hoi grace token cua integration secrets da het han.';

    public function __construct(
        protected IntegrationSecretRotationService $rotationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền thu hồi integration secret grace token.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');

        try {
            $summary = $this->rotationService->revokeExpired(
                dryRun: $dryRun,
                actorId: auth()->id(),
            );

            $this->line(sprintf(
                'dry_run=%s total_expired=%d revoked=%d',
                $dryRun ? 'yes' : 'no',
                (int) $summary['total_expired'],
                (int) $summary['revoked'],
            ));

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'integrations:revoke-rotated-secrets',
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
                    'command' => 'integrations:revoke-rotated-secrets',
                    'dry_run' => $dryRun,
                    'error' => $throwable->getMessage(),
                ],
            );

            $this->error('Không thể thu hồi grace token của integration secrets: '.$throwable->getMessage());

            return $strict ? self::FAILURE : self::SUCCESS;
        }
    }
}
