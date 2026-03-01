<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RunRestoreDrill extends Command
{
    protected $signature = 'ops:run-restore-drill
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--target= : Thu muc tao artifact restore drill (mac dinh: storage/app/restore-drill)}
        {--strict : Fail command neu restore drill khong dat}';

    protected $description = 'Thuc hien restore drill toi thieu tu backup artifact va verify checksum.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy restore drill.',
        );

        $backupPath = (string) ($this->option('path') ?: storage_path('app/backups'));
        $targetPath = (string) ($this->option('target') ?: storage_path('app/restore-drill'));

        $backupFiles = is_dir($backupPath)
            ? collect(File::files($backupPath))
                ->filter(function (\SplFileInfo $file): bool {
                    $extension = strtolower($file->getExtension());

                    return in_array($extension, ['sql', 'gz', 'zip', 'tar', 'bak'], true);
                })
                ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
                ->values()
            : collect();

        $sourceFile = $backupFiles->first();
        $drillArtifact = null;
        $sourceChecksum = null;
        $artifactChecksum = null;
        $latestAgeHours = null;
        $passed = false;

        if ($sourceFile instanceof \SplFileInfo) {
            File::ensureDirectoryExists($targetPath);

            $sourcePathname = $sourceFile->getPathname();
            $drillArtifact = $targetPath.'/restore-drill-'.now()->format('Ymd_His').'-'.$sourceFile->getFilename();

            File::copy($sourcePathname, $drillArtifact);

            $sourceChecksum = hash_file('sha256', $sourcePathname) ?: null;
            $artifactChecksum = hash_file('sha256', $drillArtifact) ?: null;
            $latestAgeHours = abs(now()->diffInHours(Carbon::createFromTimestamp($sourceFile->getMTime())));

            $passed = $sourceChecksum !== null
                && $artifactChecksum !== null
                && hash_equals($sourceChecksum, $artifactChecksum)
                && File::exists($drillArtifact)
                && ((int) File::size($drillArtifact) > 0);
        }

        $status = $passed ? 'pass' : 'fail';

        $this->line('RESTORE_DRILL_STATUS: '.$status);
        $this->line('RESTORE_DRILL_BACKUP_PATH: '.$backupPath);
        $this->line('RESTORE_DRILL_SOURCE_FILE: '.($sourceFile?->getFilename() ?? '-'));
        $this->line('RESTORE_DRILL_ARTIFACT: '.($drillArtifact ?? '-'));
        $this->line('RESTORE_DRILL_LATEST_AGE_HOURS: '.($latestAgeHours !== null ? (string) $latestAgeHours : '-'));

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $passed ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: [
                'command' => 'ops:run-restore-drill',
                'status' => $status,
                'backup_path' => $backupPath,
                'target_path' => $targetPath,
                'source_file' => $sourceFile?->getFilename(),
                'artifact_file' => $drillArtifact ? basename($drillArtifact) : null,
                'source_checksum' => $sourceChecksum,
                'artifact_checksum' => $artifactChecksum,
                'latest_age_hours' => $latestAgeHours,
            ],
        );

        if ((bool) $this->option('strict') && ! $passed) {
            $this->error('Strict mode: restore drill failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
