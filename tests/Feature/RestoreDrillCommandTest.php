<?php

use Illuminate\Support\Facades\File;

it('passes strict restore drill when backup artifact can be copied and verified', function (): void {
    $backupPath = storage_path('app/testing/restore-drill/pass/backups');
    $targetPath = storage_path('app/testing/restore-drill/pass/artifacts');

    File::ensureDirectoryExists($backupPath);
    File::ensureDirectoryExists($targetPath);

    $backupFile = $backupPath.'/crm-pass.sql';
    File::put($backupFile, '-- backup fixture for restore drill');
    touch($backupFile, now()->subHour()->getTimestamp());

    $this->artisan('ops:run-restore-drill', [
        '--path' => $backupPath,
        '--target' => $targetPath,
        '--strict' => true,
    ])
        ->expectsOutputToContain('RESTORE_DRILL_STATUS: pass')
        ->assertSuccessful();

    expect(collect(File::files($targetPath))->isNotEmpty())->toBeTrue();
});

it('fails strict restore drill when backup artifact is missing', function (): void {
    $backupPath = storage_path('app/testing/restore-drill/fail/backups');
    $targetPath = storage_path('app/testing/restore-drill/fail/artifacts');

    File::ensureDirectoryExists($backupPath);
    File::ensureDirectoryExists($targetPath);

    collect(File::files($backupPath))->each(fn (\SplFileInfo $file) => File::delete($file->getPathname()));

    $this->artisan('ops:run-restore-drill', [
        '--path' => $backupPath,
        '--target' => $targetPath,
        '--strict' => true,
    ])
        ->expectsOutputToContain('RESTORE_DRILL_STATUS: fail')
        ->expectsOutputToContain('Strict mode: restore drill failed.')
        ->assertFailed();
});
