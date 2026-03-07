<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class BackupArtifactService
{
    public function __construct(protected BackupArtifactManifestService $manifestService) {}

    /**
     * @param  array<string, mixed>  $connection
     * @return array{
     *     artifact_id:string,
     *     artifact_path:string,
     *     manifest_path:string,
     *     connection:string,
     *     driver:string,
     *     artifact_size_bytes:int,
     *     artifact_checksum_sha256:string,
     *     plaintext_size_bytes:int,
     *     plaintext_checksum_sha256:string,
     *     encrypted:true,
     *     encryption_driver:string,
     *     payload_encoding:string,
     *     source_format:string,
     *     created_at:string
     * }
     */
    public function create(array $connection, string $connectionName, string $backupPath): array
    {
        $driver = strtolower((string) ($connection['driver'] ?? ''));

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('Driver khong duoc ho tro backup artifact: '.$driver);
        }

        File::ensureDirectoryExists($backupPath);

        $payload = $driver === 'sqlite'
            ? $this->sqlitePayload($connection)
            : $this->processPayload($connection, $driver);

        $artifactId = (string) Str::ulid();
        $createdAt = now()->toIso8601String();
        $artifactFile = 'crm-backup-'.$connectionName.'-'.now()->format('Ymd_His').'-'.$artifactId.'.bak';
        $artifactPath = $backupPath.'/'.$artifactFile;
        $encodedPayload = base64_encode($payload['contents']);
        $encryptedPayload = Crypt::encryptString($encodedPayload);

        File::put($artifactPath, $encryptedPayload);

        $artifactSizeBytes = (int) File::size($artifactPath);
        $artifactChecksum = $this->manifestService->checksum($artifactPath);

        if ($artifactSizeBytes <= 0 || $artifactChecksum === null) {
            File::delete($artifactPath);

            throw new RuntimeException('Khong tao duoc encrypted backup artifact hop le.');
        }

        $manifest = [
            'artifact_id' => $artifactId,
            'created_at' => $createdAt,
            'connection' => $connectionName,
            'driver' => $driver,
            'artifact_file' => $artifactFile,
            'artifact_size_bytes' => $artifactSizeBytes,
            'artifact_checksum_sha256' => $artifactChecksum,
            'plaintext_size_bytes' => $payload['plaintext_size_bytes'],
            'plaintext_checksum_sha256' => $payload['plaintext_checksum_sha256'],
            'encrypted' => true,
            'encryption_driver' => 'laravel-encrypter',
            'payload_encoding' => 'base64',
            'source_format' => $payload['source_format'],
        ];

        try {
            $manifestPath = $this->manifestService->write($backupPath, $manifest);
        } catch (\Throwable $throwable) {
            File::delete($artifactPath);

            throw new RuntimeException('Khong ghi duoc backup manifest: '.$throwable->getMessage(), previous: $throwable);
        }

        return [
            'artifact_id' => $artifactId,
            'artifact_path' => $artifactPath,
            'manifest_path' => $manifestPath,
            'connection' => $connectionName,
            'driver' => $driver,
            'artifact_size_bytes' => $artifactSizeBytes,
            'artifact_checksum_sha256' => $artifactChecksum,
            'plaintext_size_bytes' => $payload['plaintext_size_bytes'],
            'plaintext_checksum_sha256' => $payload['plaintext_checksum_sha256'],
            'encrypted' => true,
            'encryption_driver' => 'laravel-encrypter',
            'payload_encoding' => 'base64',
            'source_format' => $payload['source_format'],
            'created_at' => $createdAt,
        ];
    }

    public function decryptArtifact(string $artifactPath): string
    {
        if (! File::exists($artifactPath)) {
            throw new RuntimeException('Khong tim thay backup artifact: '.$artifactPath);
        }

        try {
            $encodedPayload = Crypt::decryptString(File::get($artifactPath));
        } catch (DecryptException $exception) {
            throw new RuntimeException('Khong giai ma duoc backup artifact.', previous: $exception);
        }

        $payload = base64_decode($encodedPayload, true);

        if (! is_string($payload)) {
            throw new RuntimeException('Backup artifact co payload encoding khong hop le.');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{contents:string, plaintext_checksum_sha256:string, plaintext_size_bytes:int, source_format:string}
     */
    protected function sqlitePayload(array $connection): array
    {
        $database = trim((string) ($connection['database'] ?? ''));

        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('SQLite database path khong hop le.');
        }

        $sourcePath = $this->resolveDatabasePath($database);

        if (! File::exists($sourcePath)) {
            throw new RuntimeException('Khong tim thay SQLite database file: '.$sourcePath);
        }

        $contents = File::get($sourcePath);
        $checksum = hash('sha256', $contents);

        return [
            'contents' => $contents,
            'plaintext_checksum_sha256' => $checksum,
            'plaintext_size_bytes' => strlen($contents),
            'source_format' => 'sqlite-binary',
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array{contents:string, plaintext_checksum_sha256:string, plaintext_size_bytes:int, source_format:string}
     */
    protected function processPayload(array $connection, string $driver): array
    {
        $database = trim((string) ($connection['database'] ?? ''));
        $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
        $port = (string) ($connection['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
        $username = trim((string) ($connection['username'] ?? ''));
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Thong tin ket noi database khong day du de tao dump.');
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
            $environment = ['PGPASSWORD' => $password];
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
            $environment = ['MYSQL_PWD' => $password];
        }

        $result = Process::path(base_path())
            ->timeout(600)
            ->env($environment)
            ->run($command);

        return $this->dumpPayloadFromProcessResult($result);
    }

    protected function resolveDatabasePath(string $database): string
    {
        if (Str::startsWith($database, ['/']) || preg_match('/^[A-Za-z]:[\\\\\/]/', $database) === 1) {
            return $database;
        }

        return base_path($database);
    }

    /**
     * @return array{contents:string, plaintext_checksum_sha256:string, plaintext_size_bytes:int, source_format:string}
     */
    protected function dumpPayloadFromProcessResult(ProcessResultContract $result): array
    {
        if (! $result->successful()) {
            throw new RuntimeException('Dump command fail: '.Str::limit(trim($result->errorOutput()), 500));
        }

        $output = $result->output();

        if (! is_string($output) || trim($output) === '') {
            throw new RuntimeException('Dump output rong, khong tao duoc backup artifact.');
        }

        return [
            'contents' => $output,
            'plaintext_checksum_sha256' => hash('sha256', $output),
            'plaintext_size_bytes' => strlen($output),
            'source_format' => 'sql-dump',
        ];
    }
}
