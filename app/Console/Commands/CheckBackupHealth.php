<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckBackupHealth extends Command
{
    protected $signature = 'ops:check-backup-health
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--max-age-hours=26 : Gioi han tuoi backup moi nhat}
        {--strict : Fail command neu backup khong healthy}';

    protected $description = 'Kiem tra suc khoe backup truoc release gate (ton tai + do moi).';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/backups'));
        $maxAgeHours = max(1, (int) $this->option('max-age-hours'));

        $directoryExists = is_dir($path);
        $backupFiles = $directoryExists
            ? collect(File::files($path))
                ->filter(function (\SplFileInfo $file): bool {
                    $extension = strtolower($file->getExtension());

                    return in_array($extension, ['sql', 'gz', 'zip', 'tar', 'bak'], true);
                })
                ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
                ->values()
            : collect();

        $latestFile = $backupFiles->first();
        $latestTimestamp = $latestFile ? Carbon::createFromTimestamp($latestFile->getMTime()) : null;
        $latestAgeHours = $latestTimestamp ? abs(now()->diffInHours($latestTimestamp)) : null;

        $healthy = $directoryExists
            && $latestFile !== null
            && $latestAgeHours !== null
            && $latestAgeHours <= $maxAgeHours;

        $status = $healthy ? 'healthy' : 'unhealthy';

        $this->line('BACKUP_HEALTH_STATUS: '.$status);
        $this->line('BACKUP_DIRECTORY: '.$path);
        $this->line('BACKUP_FILE_COUNT: '.(string) $backupFiles->count());
        $this->line('BACKUP_LATEST_FILE: '.($latestFile?->getFilename() ?? '-'));
        $this->line('BACKUP_LATEST_AGE_HOURS: '.($latestAgeHours !== null ? (string) $latestAgeHours : '-'));
        $this->line('BACKUP_MAX_AGE_HOURS: '.(string) $maxAgeHours);

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: [
                'command' => 'ops:check-backup-health',
                'status' => $status,
                'path' => $path,
                'file_count' => $backupFiles->count(),
                'latest_file' => $latestFile?->getFilename(),
                'latest_age_hours' => $latestAgeHours,
                'max_age_hours' => $maxAgeHours,
            ],
        );

        if ((bool) $this->option('strict') && ! $healthy) {
            $this->error('Strict mode: backup health check failed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
