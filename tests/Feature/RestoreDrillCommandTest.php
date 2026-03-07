<?php

use App\Services\BackupArtifactService;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('passes strict restore drill when encrypted sqlite backup can be restored into sandbox', function (): void {
    $backupPath = storage_path('app/testing/restore-drill/pass/backups');
    $targetPath = storage_path('app/testing/restore-drill/pass/artifacts');

    provisionEncryptedSqliteRestoreBackup($backupPath);
    File::deleteDirectory($targetPath);
    File::ensureDirectoryExists($targetPath);

    $this->artisan('ops:run-restore-drill', [
        '--path' => $backupPath,
        '--target' => $targetPath,
        '--strict' => true,
    ])
        ->expectsOutputToContain('RESTORE_DRILL_STATUS: pass')
        ->expectsOutputToContain('RESTORE_DRILL_MODE: sqlite-sandbox')
        ->assertSuccessful();

    expect(collect(File::files($targetPath))->first(fn (\SplFileInfo $file): bool => $file->getExtension() === 'sqlite'))->not->toBeNull();
});

it('passes strict restore drill when encrypted mysql dump can be imported into sandbox', function (): void {
    $backupPath = storage_path('app/testing/restore-drill/mysql/backups');
    $targetPath = storage_path('app/testing/restore-drill/mysql/artifacts');

    File::deleteDirectory($backupPath);
    File::deleteDirectory($targetPath);
    File::ensureDirectoryExists($backupPath);
    File::ensureDirectoryExists($targetPath);

    Process::fake(function (PendingProcess $process): ProcessResultContract {
        $command = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

        return match (true) {
            str_contains($command, 'mysqldump') => Process::result(
                output: '-- mysql restore drill fixture'.PHP_EOL.'CREATE TABLE patients (id int);',
                errorOutput: '',
                exitCode: 0,
            ),
            str_contains($command, '--execute=CREATE DATABASE') => Process::result(output: '', errorOutput: '', exitCode: 0),
            str_contains($command, '--execute=SHOW TABLES') => Process::result(output: 'patients'.PHP_EOL, errorOutput: '', exitCode: 0),
            str_contains($command, '--execute=DROP DATABASE IF EXISTS') => Process::result(output: '', errorOutput: '', exitCode: 0),
            str_contains($command, 'mysql --host=127.0.0.1 --port=3306 --user=root restore_drill_') => Process::result(output: '', errorOutput: '', exitCode: 0),
            default => Process::result(output: '', errorOutput: 'unexpected command: '.$command, exitCode: 1),
        };
    });

    config()->set('database.connections.mysql_restore_drill_test', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'crm_test',
        'username' => 'root',
        'password' => 'secret',
    ]);

    app(BackupArtifactService::class)->create(
        connection: config('database.connections.mysql_restore_drill_test'),
        connectionName: 'mysql_restore_drill_test',
        backupPath: $backupPath,
    );

    $this->artisan('ops:run-restore-drill', [
        '--path' => $backupPath,
        '--target' => $targetPath,
        '--strict' => true,
    ])
        ->expectsOutputToContain('RESTORE_DRILL_STATUS: pass')
        ->expectsOutputToContain('RESTORE_DRILL_MODE: mysql-sandbox')
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command) && in_array('mysqldump', $process->command, true));
    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command) && collect($process->command)->contains(fn (string $segment): bool => str_contains($segment, '--execute=CREATE DATABASE')));
    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command) && collect($process->command)->contains(fn (string $segment): bool => str_contains($segment, '--execute=SHOW TABLES')));
});

it('fails strict restore drill when backup manifest is missing', function (): void {
    $backupPath = storage_path('app/testing/restore-drill/fail/backups');
    $targetPath = storage_path('app/testing/restore-drill/fail/artifacts');

    File::deleteDirectory($backupPath);
    File::deleteDirectory($targetPath);
    File::ensureDirectoryExists($backupPath);
    File::ensureDirectoryExists($targetPath);

    $this->artisan('ops:run-restore-drill', [
        '--path' => $backupPath,
        '--target' => $targetPath,
        '--strict' => true,
    ])
        ->expectsOutputToContain('RESTORE_DRILL_STATUS: fail')
        ->expectsOutputToContain('RESTORE_DRILL_ERROR: backup_manifest_missing')
        ->expectsOutputToContain('Strict mode: restore drill failed.')
        ->assertFailed();
});

function provisionEncryptedSqliteRestoreBackup(string $backupPath): void
{
    $sourceDirectory = $backupPath.'/source';
    $databasePath = $sourceDirectory.'/database.sqlite';
    $connectionName = 'sqlite_restore_drill_'.str()->random(10);

    File::deleteDirectory($backupPath);
    File::ensureDirectoryExists($sourceDirectory);

    $pdo = new PDO('sqlite:'.$databasePath);
    $pdo->exec('CREATE TABLE patients (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    $pdo->exec("INSERT INTO patients (name) VALUES ('Restore Drill')");
    $pdo = null;

    config()->set('database.connections.'.$connectionName, [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    app(BackupArtifactService::class)->create(
        connection: config('database.connections.'.$connectionName),
        connectionName: $connectionName,
        backupPath: $backupPath,
    );
}
