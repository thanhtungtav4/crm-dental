<?php

use App\Services\BackupArtifactService;
use Illuminate\Support\Facades\File;

it('passes strict backup health check when latest encrypted backup manifest is fresh', function (): void {
    $backupPath = storage_path('app/testing/backup-health/pass');

    provisionEncryptedBackupArtifact($backupPath, 'fresh-backup-fixture');

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 2,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: healthy')
        ->expectsOutputToContain('BACKUP_ARTIFACT_ENCRYPTED: yes')
        ->expectsOutputToContain('BACKUP_ARTIFACT_CHECKSUM_VALID: yes')
        ->assertSuccessful();
});

it('fails strict backup health check when latest manifest is stale', function (): void {
    $backupPath = storage_path('app/testing/backup-health/fail');
    $artifact = provisionEncryptedBackupArtifact($backupPath, 'stale-backup-fixture');

    $manifest = json_decode(File::get($artifact['manifest_path']), true, 512, JSON_THROW_ON_ERROR);
    $manifest['created_at'] = now()->subDays(3)->toIso8601String();
    File::put(
        $artifact['manifest_path'],
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    );

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 24,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: unhealthy')
        ->expectsOutputToContain('BACKUP_HEALTH_ERROR: backup_artifact_stale')
        ->expectsOutputToContain('Strict mode: backup health check failed.')
        ->assertFailed();
});

it('fails strict backup health check when manifest checksum does not match artifact', function (): void {
    $backupPath = storage_path('app/testing/backup-health/checksum-mismatch');
    $artifact = provisionEncryptedBackupArtifact($backupPath, 'checksum-backup-fixture');

    $artifactSize = (int) File::size($artifact['artifact_path']);
    File::put($artifact['artifact_path'], str_repeat('x', $artifactSize));

    $this->artisan('ops:check-backup-health', [
        '--path' => $backupPath,
        '--max-age-hours' => 24,
        '--strict' => true,
    ])
        ->expectsOutputToContain('BACKUP_HEALTH_STATUS: unhealthy')
        ->expectsOutputToContain('BACKUP_HEALTH_ERROR: backup_artifact_checksum_mismatch')
        ->expectsOutputToContain('Strict mode: backup health check failed.')
        ->assertFailed();
});

/**
 * @return array{artifact_path:string, manifest_path:string}
 */
function provisionEncryptedBackupArtifact(string $backupPath, string $payload): array
{
    $sourceDirectory = $backupPath.'/source';
    $databasePath = $sourceDirectory.'/database.sqlite';
    $connectionName = 'sqlite_backup_health_'.str()->random(10);

    File::deleteDirectory($backupPath);
    File::ensureDirectoryExists($sourceDirectory);
    File::put($databasePath, $payload);

    config()->set('database.connections.'.$connectionName, [
        'driver' => 'sqlite',
        'database' => $databasePath,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $artifact = app(BackupArtifactService::class)->create(
        connection: config('database.connections.'.$connectionName),
        connectionName: $connectionName,
        backupPath: $backupPath,
    );

    return [
        'artifact_path' => $artifact['artifact_path'],
        'manifest_path' => $artifact['manifest_path'],
    ];
}
