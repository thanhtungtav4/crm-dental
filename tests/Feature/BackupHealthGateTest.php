<?php

use Illuminate\Support\Facades\File;

it('passes strict backup health check when latest backup is fresh', function (): void {
    $backupPath = storage_path('app/testing/backup-health/pass');
    File::ensureDirectoryExists($backupPath);

    $backupFile = $backupPath.'/crm-backup-pass.sql';
    File::put($backupFile, '-- backup fixture');
    touch($backupFile, now()->subHour()->getTimestamp());

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 2,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: healthy')
        ->assertSuccessful();
});

it('fails strict backup health check when latest backup is stale', function (): void {
    $backupPath = storage_path('app/testing/backup-health/fail');
    File::ensureDirectoryExists($backupPath);

    $backupFile = $backupPath.'/crm-backup-fail.sql';
    File::put($backupFile, '-- backup fixture');
    touch($backupFile, now()->subDays(3)->getTimestamp());

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 24,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: unhealthy')
        ->expectsOutputToContain('Strict mode: backup health check failed.')
        ->assertFailed();
});
