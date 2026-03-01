<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class CreateBackupArtifact extends Command
{
    protected $signature = 'ops:create-backup-artifact
        {--path= : Thu muc backup (mac dinh: storage/app/backups)}
        {--connection= : Database connection backup (mac dinh: database.default)}
        {--strict : Fail command neu tao backup artifact khong thanh cong}';

    protected $description = 'Tao backup artifact toi thieu de phuc vu release gate va restore drill.';

    public function handle(): int
    {
        $backupPath = (string) ($this->option('path') ?: storage_path('app/backups'));
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection) || $connection === []) {
            return $this->failBackupArtifact(
                message: 'Khong tim thay database connection: '.$connectionName,
                backupPath: $backupPath,
                connectionName: $connectionName,
                driver: null,
            );
        }

        $driver = strtolower((string) ($connection['driver'] ?? ''));
        File::ensureDirectoryExists($backupPath);

        $timestamp = now()->format('Ymd_His');
        $artifactPath = null;
        $error = null;

        try {
            if ($driver === 'sqlite') {
                $artifactPath = $this->backupSqliteConnection(
                    connection: $connection,
                    connectionName: $connectionName,
                    backupPath: $backupPath,
                    timestamp: $timestamp,
                );
            } elseif (in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
                $artifactPath = $this->backupProcessConnection(
                    connection: $connection,
                    connectionName: $connectionName,
                    backupPath: $backupPath,
                    timestamp: $timestamp,
                    driver: $driver,
                );
            } else {
                return $this->failBackupArtifact(
                    message: 'Driver khong duoc ho tro backup artifact: '.$driver,
                    backupPath: $backupPath,
                    connectionName: $connectionName,
                    driver: $driver,
                );
            }
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
        }

        $artifactExists = is_string($artifactPath) && File::exists($artifactPath);
        $artifactSize = $artifactExists ? (int) File::size($artifactPath) : 0;
        $successful = $artifactExists && $artifactSize > 0 && $error === null;
        $status = $successful ? 'success' : 'failed';

        $this->line('BACKUP_ARTIFACT_STATUS: '.$status);
        $this->line('BACKUP_ARTIFACT_CONNECTION: '.$connectionName);
        $this->line('BACKUP_ARTIFACT_DRIVER: '.($driver !== '' ? $driver : '-'));
        $this->line('BACKUP_ARTIFACT_PATH: '.($artifactPath ?? '-'));
        $this->line('BACKUP_ARTIFACT_SIZE_BYTES: '.($artifactExists ? (string) $artifactSize : '-'));
        $this->line('BACKUP_ARTIFACT_ERROR: '.($error ?? '-'));

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $successful ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: [
                'command' => 'ops:create-backup-artifact',
                'status' => $status,
                'backup_path' => $backupPath,
                'connection' => $connectionName,
                'driver' => $driver !== '' ? $driver : null,
                'artifact_file' => $artifactPath ? basename($artifactPath) : null,
                'artifact_size_bytes' => $artifactSize,
                'error' => $error,
            ],
        );

        if ((bool) $this->option('strict') && ! $successful) {
            $this->error('Strict mode: tao backup artifact that bai.');

            return self::FAILURE;
        }

        return $successful ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function backupSqliteConnection(
        array $connection,
        string $connectionName,
        string $backupPath,
        string $timestamp,
    ): string {
        $database = trim((string) ($connection['database'] ?? ''));

        if ($database === '' || $database === ':memory:') {
            throw new \RuntimeException('SQLite database path khong hop le.');
        }

        $sourcePath = $this->resolveDatabasePath($database);

        if (! File::exists($sourcePath)) {
            throw new \RuntimeException('Khong tim thay SQLite database file: '.$sourcePath);
        }

        $artifactPath = $backupPath.'/crm-backup-'.$connectionName.'-'.$timestamp.'.bak';
        File::copy($sourcePath, $artifactPath);

        return $artifactPath;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function backupProcessConnection(
        array $connection,
        string $connectionName,
        string $backupPath,
        string $timestamp,
        string $driver,
    ): string {
        $database = trim((string) ($connection['database'] ?? ''));
        $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
        $port = (string) ($connection['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
        $username = trim((string) ($connection['username'] ?? ''));
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new \RuntimeException('Thong tin ket noi database khong day du de tao dump.');
        }

        if ($driver === 'pgsql') {
            $command = [
                'pg_dump',
                '--format=plain',
                '--no-owner',
                '--no-privileges',
                '--host='.$host,
                '--port='.$port,
                '--username='.$username,
                $database,
            ];
            $env = ['PGPASSWORD' => $password];
            $extension = 'sql';
        } else {
            $command = [
                'mysqldump',
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--host='.$host,
                '--port='.$port,
                '--user='.$username,
                $database,
            ];
            $env = ['MYSQL_PWD' => $password];
            $extension = 'sql';
        }

        $result = Process::path(base_path())
            ->timeout(600)
            ->env($env)
            ->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException('Dump command fail: '.Str::limit(trim($result->errorOutput()), 500));
        }

        $output = $result->output();
        if (! is_string($output) || trim($output) === '') {
            throw new \RuntimeException('Dump output rong, khong tao duoc backup artifact.');
        }

        $artifactPath = $backupPath.'/crm-backup-'.$connectionName.'-'.$timestamp.'.'.$extension;
        File::put($artifactPath, $output);

        return $artifactPath;
    }

    protected function resolveDatabasePath(string $database): string
    {
        if (Str::startsWith($database, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $database) === 1) {
            return $database;
        }

        return base_path($database);
    }

    protected function failBackupArtifact(string $message, string $backupPath, string $connectionName, ?string $driver): int
    {
        $this->line('BACKUP_ARTIFACT_STATUS: failed');
        $this->line('BACKUP_ARTIFACT_CONNECTION: '.$connectionName);
        $this->line('BACKUP_ARTIFACT_DRIVER: '.($driver ?? '-'));
        $this->line('BACKUP_ARTIFACT_PATH: -');
        $this->line('BACKUP_ARTIFACT_SIZE_BYTES: -');
        $this->line('BACKUP_ARTIFACT_ERROR: '.$message);

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
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
