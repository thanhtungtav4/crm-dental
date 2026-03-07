<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\BackupArtifactService;
use App\Services\OpsCommandAuthorizer;
use Illuminate\Console\Command;

class CreateBackupArtifact extends Command
{
    protected $signature = 'ops:create-backup-artifact
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--connection= : Database connection backup (mac dinh: database.default)}
        {--strict : Fail command neu tao backup artifact khong thanh cong}';

    protected $description = 'Tao encrypted backup artifact va manifest de phuc vu release gate va restore drill.';

    public function __construct(
        protected OpsCommandAuthorizer $authorizer,
        protected BackupArtifactService $backupArtifactService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $actorId = $this->authorizer->authorize(
            'Bạn không có quyền tạo backup artifact.',
        );

        $backupPath = (string) ($this->option('path') ?: storage_path('app/backups'));
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection) || $connection === []) {
            return $this->failBackupArtifact(
                message: 'Khong tim thay database connection: '.$connectionName,
                backupPath: $backupPath,
                connectionName: $connectionName,
                driver: null,
                actorId: $actorId,
            );
        }

        $artifact = null;
        $error = null;
        $driver = strtolower((string) ($connection['driver'] ?? ''));

        try {
            $artifact = $this->backupArtifactService->create(
                connection: $connection,
                connectionName: $connectionName,
                backupPath: $backupPath,
            );
        } catch (\Throwable $throwable) {
            $error = $throwable->getMessage();
        }

        $successful = is_array($artifact) && $error === null;
        $status = $successful ? 'success' : 'failed';

        $this->line('BACKUP_ARTIFACT_STATUS: '.$status);
        $this->line('BACKUP_ARTIFACT_CONNECTION: '.$connectionName);
        $this->line('BACKUP_ARTIFACT_DRIVER: '.($driver !== '' ? $driver : '-'));
        $this->line('BACKUP_ARTIFACT_PATH: '.($artifact['artifact_path'] ?? '-'));
        $this->line('BACKUP_ARTIFACT_SIZE_BYTES: '.(isset($artifact['artifact_size_bytes']) ? (string) $artifact['artifact_size_bytes'] : '-'));
        $this->line('BACKUP_ARTIFACT_MANIFEST_PATH: '.($artifact['manifest_path'] ?? '-'));
        $this->line('BACKUP_ARTIFACT_ENCRYPTED: '.($successful ? 'yes' : '-'));
        $this->line('BACKUP_ARTIFACT_CHECKSUM_SHA256: '.($artifact['artifact_checksum_sha256'] ?? '-'));
        $this->line('BACKUP_ARTIFACT_ERROR: '.($error ?? '-'));

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $successful ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: $actorId,
            metadata: [
                'command' => 'ops:create-backup-artifact',
                'status' => $status,
                'backup_path' => $backupPath,
                'connection' => $connectionName,
                'driver' => $driver !== '' ? $driver : null,
                'artifact_id' => $artifact['artifact_id'] ?? null,
                'artifact_file' => isset($artifact['artifact_path']) ? basename((string) $artifact['artifact_path']) : null,
                'artifact_size_bytes' => $artifact['artifact_size_bytes'] ?? 0,
                'artifact_checksum_sha256' => $artifact['artifact_checksum_sha256'] ?? null,
                'manifest_file' => isset($artifact['manifest_path']) ? basename((string) $artifact['manifest_path']) : null,
                'plaintext_size_bytes' => $artifact['plaintext_size_bytes'] ?? null,
                'plaintext_checksum_sha256' => $artifact['plaintext_checksum_sha256'] ?? null,
                'encrypted' => $artifact['encrypted'] ?? false,
                'encryption_driver' => $artifact['encryption_driver'] ?? null,
                'payload_encoding' => $artifact['payload_encoding'] ?? null,
                'source_format' => $artifact['source_format'] ?? null,
                'error' => $error,
            ],
        );

        if ((bool) $this->option('strict') && ! $successful) {
            $this->error('Strict mode: tao backup artifact that bai.');

            return self::FAILURE;
        }

        return $successful ? self::SUCCESS : self::FAILURE;
    }

    protected function failBackupArtifact(string $message, string $backupPath, string $connectionName, ?string $driver, ?int $actorId): int
    {
        $this->line('BACKUP_ARTIFACT_STATUS: failed');
        $this->line('BACKUP_ARTIFACT_CONNECTION: '.$connectionName);
        $this->line('BACKUP_ARTIFACT_DRIVER: '.($driver ?? '-'));
        $this->line('BACKUP_ARTIFACT_PATH: -');
        $this->line('BACKUP_ARTIFACT_SIZE_BYTES: -');
        $this->line('BACKUP_ARTIFACT_MANIFEST_PATH: -');
        $this->line('BACKUP_ARTIFACT_ENCRYPTED: -');
        $this->line('BACKUP_ARTIFACT_CHECKSUM_SHA256: -');
        $this->line('BACKUP_ARTIFACT_ERROR: '.$message);

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_FAIL,
            actorId: $actorId,
            metadata: [
                'command' => 'ops:create-backup-artifact',
                'status' => 'failed',
                'backup_path' => $backupPath,
                'connection' => $connectionName,
                'driver' => $driver,
                'error' => $message,
            ],
        );

        if ((bool) $this->option('strict')) {
            $this->error('Strict mode: tao backup artifact that bai.');
        }

        return self::FAILURE;
    }
}
