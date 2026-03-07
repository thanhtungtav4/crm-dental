<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\BackupArtifactManifestService;
use App\Services\OpsCommandAuthorizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckBackupHealth extends Command
{
    protected $signature = 'ops:check-backup-health
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--max-age-hours=26 : Gioi han tuoi backup moi nhat}
        {--strict : Fail command neu backup khong healthy}';

    protected $description = 'Kiem tra suc khoe backup dua tren encrypted artifact + manifest truoc release gate.';

    public function __construct(
        protected OpsCommandAuthorizer $authorizer,
        protected BackupArtifactManifestService $manifestService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $actorId = $this->authorizer->authorize(
            'Bạn không có quyền kiểm tra backup health.',
        );

        $path = (string) ($this->option('path') ?: storage_path('app/backups'));
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
        $directoryExists = is_dir($path);
        $latestManifest = $this->manifestService->latest($path);
        $manifestPath = is_array($latestManifest) ? $latestManifest['path'] ?? null : null;
        $manifest = is_array($latestManifest) ? ($latestManifest['data'] ?? null) : null;
        $validationErrors = $this->manifestService->validate(is_array($manifest) ? $manifest : null);
        $artifactPath = is_array($manifest) ? $this->manifestService->artifactPath($path, $manifest) : null;
        $artifactExists = is_string($artifactPath) && File::exists($artifactPath);
        $artifactSizeBytes = $artifactExists ? (int) File::size($artifactPath) : 0;
        $artifactChecksum = $artifactExists && is_string($artifactPath)
            ? $this->manifestService->checksum($artifactPath)
            : null;
        $expectedChecksum = is_array($manifest) ? (string) ($manifest['artifact_checksum_sha256'] ?? '') : '';
        $checksumValid = $artifactChecksum !== null && $expectedChecksum !== '' && hash_equals($expectedChecksum, $artifactChecksum);
        $expectedArtifactSize = is_array($manifest) ? (int) ($manifest['artifact_size_bytes'] ?? 0) : 0;
        $sizeValid = $artifactExists && $artifactSizeBytes > 0 && $expectedArtifactSize > 0 && $artifactSizeBytes === $expectedArtifactSize;
        $createdAt = is_array($manifest) ? $this->manifestService->createdAt($manifest) : null;
        $latestAgeHours = $createdAt?->diffInHours(now(), absolute: true);
        $healthy = $directoryExists
            && $manifestPath !== null
            && $validationErrors === []
            && $artifactExists
            && $sizeValid
            && $checksumValid
            && $latestAgeHours !== null
            && $latestAgeHours <= $maxAgeHours;
        $status = $healthy ? 'healthy' : 'unhealthy';
        $errorCode = $healthy ? null : $this->resolveErrorCode(
            directoryExists: $directoryExists,
            manifestPath: $manifestPath,
            validationErrors: $validationErrors,
            artifactExists: $artifactExists,
            sizeValid: $sizeValid,
            checksumValid: $checksumValid,
            latestAgeHours: $latestAgeHours,
            maxAgeHours: $maxAgeHours,
        );
        $manifestCount = $directoryExists
            ? collect(File::files($path))
                ->filter(fn (\SplFileInfo $file): bool => $this->manifestService->isManifestFile($file))
                ->count()
            : 0;

        $this->line('BACKUP_HEALTH_STATUS: '.$status);
        $this->line('BACKUP_DIRECTORY: '.$path);
        $this->line('BACKUP_MANIFEST_COUNT: '.(string) $manifestCount);
        $this->line('BACKUP_LATEST_MANIFEST: '.(is_string($manifestPath) ? basename($manifestPath) : '-'));
        $this->line('BACKUP_LATEST_FILE: '.($artifactExists && is_string($artifactPath) ? basename($artifactPath) : '-'));
        $this->line('BACKUP_LATEST_AGE_HOURS: '.($latestAgeHours !== null ? (string) $latestAgeHours : '-'));
        $this->line('BACKUP_MAX_AGE_HOURS: '.(string) $maxAgeHours);
        $this->line('BACKUP_ARTIFACT_ENCRYPTED: '.(is_array($manifest) && ($manifest['encrypted'] ?? false) === true ? 'yes' : 'no'));
        $this->line('BACKUP_ARTIFACT_CHECKSUM_VALID: '.($checksumValid ? 'yes' : 'no'));
        $this->line('BACKUP_HEALTH_ERROR: '.($errorCode ?? '-'));

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: $actorId,
            metadata: [
                'command' => 'ops:check-backup-health',
                'status' => $status,
                'path' => $path,
                'manifest_count' => $manifestCount,
                'manifest_file' => is_string($manifestPath) ? basename($manifestPath) : null,
                'artifact_file' => $artifactExists && is_string($artifactPath) ? basename($artifactPath) : null,
                'artifact_checksum_valid' => $checksumValid,
                'artifact_size_valid' => $sizeValid,
                'latest_age_hours' => $latestAgeHours,
                'max_age_hours' => $maxAgeHours,
                'error_code' => $errorCode,
                'validation_errors' => $validationErrors,
            ],
        );

        if ((bool) $this->option('strict') && ! $healthy) {
            $this->error('Strict mode: backup health check failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $validationErrors
     */
    protected function resolveErrorCode(
        bool $directoryExists,
        ?string $manifestPath,
        array $validationErrors,
        bool $artifactExists,
        bool $sizeValid,
        bool $checksumValid,
        ?int $latestAgeHours,
        int $maxAgeHours,
    ): string {
        if (! $directoryExists) {
            return 'backup_directory_missing';
        }

        if ($manifestPath === null) {
            return 'backup_manifest_missing';
        }

        if ($validationErrors !== []) {
            return $validationErrors[0];
        }

        if (! $artifactExists) {
            return 'backup_artifact_missing';
        }

        if (! $sizeValid) {
            return 'backup_artifact_size_invalid';
        }

        if (! $checksumValid) {
            return 'backup_artifact_checksum_mismatch';
        }

        if ($latestAgeHours === null || $latestAgeHours > $maxAgeHours) {
            return 'backup_artifact_stale';
        }

        return 'backup_unhealthy';
    }
}
