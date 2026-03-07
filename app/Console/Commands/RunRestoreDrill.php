<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\OpsCommandAuthorizer;
use App\Services\RestoreDrillService;
use Illuminate\Console\Command;

class RunRestoreDrill extends Command
{
    protected $signature = 'ops:run-restore-drill
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--target= : Thu muc tao artifact restore drill (mac dinh: storage/app/restore-drill)}
        {--strict : Fail command neu restore drill khong dat}';

    protected $description = 'Thuc hien restore drill tu encrypted backup artifact, verify checksum va restore sandbox.';

    public function __construct(
        protected OpsCommandAuthorizer $authorizer,
        protected RestoreDrillService $restoreDrillService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $actorId = $this->authorizer->authorize(
            'Bạn không có quyền chạy restore drill.',
        );

        $backupPath = (string) ($this->option('path') ?: storage_path('app/backups'));
        $targetPath = (string) ($this->option('target') ?: storage_path('app/restore-drill'));
        $result = $this->restoreDrillService->run($backupPath, $targetPath);
        $passed = ($result['status'] ?? 'fail') === 'pass';

        $this->line('RESTORE_DRILL_STATUS: '.($result['status'] ?? 'fail'));
        $this->line('RESTORE_DRILL_BACKUP_PATH: '.$backupPath);
        $this->line('RESTORE_DRILL_MANIFEST: '.($result['manifest_path'] ? basename((string) $result['manifest_path']) : '-'));
        $this->line('RESTORE_DRILL_SOURCE_FILE: '.($result['source_file'] ?: '-'));
        $this->line('RESTORE_DRILL_ARTIFACT: '.($result['drill_artifact'] ?: '-'));
        $this->line('RESTORE_DRILL_MODE: '.($result['restore_mode'] ?: '-'));
        $this->line('RESTORE_DRILL_LATEST_AGE_HOURS: '.($result['latest_age_hours'] !== null ? (string) $result['latest_age_hours'] : '-'));
        $this->line('RESTORE_DRILL_ERROR: '.($result['error'] ?: '-'));

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $passed ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: $actorId,
            metadata: [
                'command' => 'ops:run-restore-drill',
                'status' => $result['status'] ?? 'fail',
                'backup_path' => $backupPath,
                'target_path' => $targetPath,
                'manifest_file' => $result['manifest_path'] ? basename((string) $result['manifest_path']) : null,
                'source_file' => $result['source_file'],
                'artifact_file' => $result['artifact_file'],
                'drill_artifact' => $result['drill_artifact'] ? basename((string) $result['drill_artifact']) : null,
                'restore_mode' => $result['restore_mode'],
                'artifact_checksum_sha256' => $result['artifact_checksum_sha256'],
                'payload_checksum_sha256' => $result['payload_checksum_sha256'],
                'latest_age_hours' => $result['latest_age_hours'],
                'error' => $result['error'],
            ],
        );

        if ((bool) $this->option('strict') && ! $passed) {
            $this->error('Strict mode: restore drill failed.');

            return self::FAILURE;
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
