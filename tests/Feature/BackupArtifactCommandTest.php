<?php

use App\Services\BackupArtifactService;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('creates sqlite backup artifact with encrypted payload and manifest', function (): void {
    $sourceDirectory = storage_path('app/testing/backup-artifact/sqlite-source');
    $backupDirectory = storage_path('app/testing/backup-artifact/sqlite-output');
    $sourceFile = $sourceDirectory.'/database.sqlite';

    File::deleteDirectory($sourceDirectory);
    File::deleteDirectory($backupDirectory);
    File::ensureDirectoryExists($sourceDirectory);
    File::ensureDirectoryExists($backupDirectory);
    File::put($sourceFile, 'sqlite-backup-fixture');

    config()->set('database.connections.sqlite_backup_test', [
        'driver' => 'sqlite',
        'database' => $sourceFile,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $this->artisan('ops:create-backup-artifact', [
        '--connection' => 'sqlite_backup_test',
        '--path' => $backupDirectory,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_ARTIFACT_STATUS: success')
        ->expectsOutputToContain('BACKUP_ARTIFACT_ENCRYPTED: yes')
        ->assertSuccessful();

    $artifact = collect(File::files($backupDirectory))
        ->first(fn (\SplFileInfo $file): bool => $file->getExtension() === 'bak');
    $manifest = collect(File::files($backupDirectory))
        ->first(fn (\SplFileInfo $file): bool => str($file->getFilename())->endsWith('.manifest.json'));

    expect($artifact)->not->toBeNull()
        ->and($manifest)->not->toBeNull()
        ->and(File::get($artifact->getPathname()))->not->toBe('sqlite-backup-fixture');

    $decrypted = app(BackupArtifactService::class)->decryptArtifact($artifact->getPathname());
    $manifestPayload = json_decode(File::get($manifest->getPathname()), true, 512, JSON_THROW_ON_ERROR);

    expect($decrypted)->toBe('sqlite-backup-fixture')
        ->and((bool) ($manifestPayload['encrypted'] ?? false))->toBeTrue()
        ->and((string) ($manifestPayload['artifact_file'] ?? ''))->toBe($artifact->getFilename())
        ->and((string) ($manifestPayload['plaintext_checksum_sha256'] ?? ''))->toBe(hash('sha256', 'sqlite-backup-fixture'));
});

it('creates mysql backup artifact from mysqldump process output and records manifest', function (): void {
    $backupDirectory = storage_path('app/testing/backup-artifact/mysql-output');

    File::deleteDirectory($backupDirectory);
    File::ensureDirectoryExists($backupDirectory);

    Process::fake([
        '*' => Process::result(
            output: '-- mysql dump fixture'.PHP_EOL.'CREATE TABLE test(id int);',
            errorOutput: '',
            exitCode: 0,
        ),
    ]);

    config()->set('database.connections.mysql_backup_test', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'crm_test',
        'username' => 'root',
        'password' => 'secret',
    ]);

    $this->artisan('ops:create-backup-artifact', [
        '--connection' => 'mysql_backup_test',
        '--path' => $backupDirectory,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_ARTIFACT_STATUS: success')
        ->expectsOutputToContain('BACKUP_ARTIFACT_ENCRYPTED: yes')
        ->assertSuccessful();

    Process::assertRan(function (PendingProcess $process, ProcessResultContract $result): bool {
        return is_array($process->command)
            && in_array('mysqldump', $process->command, true)
            && in_array('--single-transaction', $process->command, true);
    });

    $artifact = collect(File::files($backupDirectory))
        ->first(fn (\SplFileInfo $file): bool => $file->getExtension() === 'bak');
    $manifest = collect(File::files($backupDirectory))
        ->first(fn (\SplFileInfo $file): bool => str($file->getFilename())->endsWith('.manifest.json'));

    expect($artifact)->not->toBeNull()
        ->and($manifest)->not->toBeNull();

    $decrypted = app(BackupArtifactService::class)->decryptArtifact($artifact->getPathname());

    expect($decrypted)->toContain('-- mysql dump fixture');
});

it('fails strict mode when dump process fails', function (): void {
    $backupDirectory = storage_path('app/testing/backup-artifact/fail-output');

    File::deleteDirectory($backupDirectory);
    File::ensureDirectoryExists($backupDirectory);

    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'mysqldump: access denied',
            exitCode: 1,
        ),
    ]);

    config()->set('database.connections.mysql_backup_fail', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'crm_test',
        'username' => 'root',
        'password' => 'bad-secret',
    ]);

    $this->artisan('ops:create-backup-artifact', [
        '--connection' => 'mysql_backup_fail',
        '--path' => $backupDirectory,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_ARTIFACT_STATUS: failed')
        ->expectsOutputToContain('Strict mode: tao backup artifact that bai.')
        ->assertFailed();
});
