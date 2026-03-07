<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult as ProcessResultContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class RestoreDrillService
{
    public function __construct(
        protected BackupArtifactManifestService $manifestService,
        protected BackupArtifactService $backupArtifactService,
    ) {}

    /**
     * @return array{
     *     status:string,
     *     backup_path:string,
     *     target_path:string,
     *     manifest_path:string|null,
     *     source_file:string|null,
     *     artifact_file:string|null,
     *     drill_artifact:string|null,
     *     latest_age_hours:int|null,
     *     restore_mode:string|null,
     *     artifact_checksum_sha256:string|null,
     *     payload_checksum_sha256:string|null,
     *     error:string|null
     * }
     */
    public function run(string $backupPath, string $targetPath): array
    {
        $latestManifest = $this->manifestService->latest($backupPath);
        $manifestPath = is_array($latestManifest) ? ($latestManifest['path'] ?? null) : null;
        $manifest = is_array($latestManifest) ? ($latestManifest['data'] ?? null) : null;
        $validationErrors = $this->manifestService->validate(is_array($manifest) ? $manifest : null);
        $artifactPath = is_array($manifest) ? $this->manifestService->artifactPath($backupPath, $manifest) : null;
        $createdAt = is_array($manifest) ? $this->manifestService->createdAt($manifest) : null;
        $latestAgeHours = $createdAt?->diffInHours(now(), absolute: true);
        $driver = is_array($manifest) ? (string) ($manifest['driver'] ?? '') : '';

        $result = [
            'status' => 'fail',
            'backup_path' => $backupPath,
            'target_path' => $targetPath,
            'manifest_path' => is_string($manifestPath) ? $manifestPath : null,
            'source_file' => is_array($manifest) ? (string) ($manifest['artifact_file'] ?? '') : null,
            'artifact_file' => is_array($manifest) ? (string) ($manifest['artifact_file'] ?? '') : null,
            'drill_artifact' => null,
            'latest_age_hours' => $latestAgeHours,
            'restore_mode' => null,
            'artifact_checksum_sha256' => null,
            'payload_checksum_sha256' => null,
            'error' => null,
        ];

        if (! is_string($manifestPath) || $manifestPath === '') {
            $result['error'] = 'backup_manifest_missing';

            return $result;
        }

        if ($validationErrors !== []) {
            $result['error'] = $validationErrors[0];

            return $result;
        }

        if (! is_string($artifactPath) || ! File::exists($artifactPath)) {
            $result['error'] = 'backup_artifact_missing';

            return $result;
        }

        $artifactChecksum = $this->manifestService->checksum($artifactPath);
        $expectedArtifactChecksum = (string) ($manifest['artifact_checksum_sha256'] ?? '');

        if ($artifactChecksum === null || $expectedArtifactChecksum === '' || ! hash_equals($expectedArtifactChecksum, $artifactChecksum)) {
            $result['error'] = 'backup_artifact_checksum_mismatch';
            $result['artifact_checksum_sha256'] = $artifactChecksum;

            return $result;
        }

        $payload = $this->backupArtifactService->decryptArtifact($artifactPath);
        $payloadChecksum = hash('sha256', $payload);
        $expectedPayloadChecksum = (string) ($manifest['plaintext_checksum_sha256'] ?? '');

        $result['artifact_checksum_sha256'] = $artifactChecksum;
        $result['payload_checksum_sha256'] = $payloadChecksum;

        if ($expectedPayloadChecksum === '' || ! hash_equals($expectedPayloadChecksum, $payloadChecksum)) {
            $result['error'] = 'backup_payload_checksum_mismatch';

            return $result;
        }

        File::ensureDirectoryExists($targetPath);

        if (($manifest['source_format'] ?? null) === 'sqlite-binary' || $driver === 'sqlite') {
            return $this->runSqliteRestoreDrill($manifest, $payload, $result, $targetPath);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->runMysqlRestoreDrill($manifest, $payload, $result, $targetPath);
        }

        if ($driver === 'pgsql') {
            return $this->runPgsqlRestoreDrill($manifest, $payload, $result, $targetPath);
        }

        $result['error'] = 'restore_driver_not_supported';

        return $result;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function runSqliteRestoreDrill(array $manifest, string $payload, array $result, string $targetPath): array
    {
        $artifactId = (string) ($manifest['artifact_id'] ?? Str::ulid());
        $restorePath = $targetPath.'/restore-drill-'.$artifactId.'.sqlite';
        $connectionName = 'restore_drill_'.Str::lower(Str::random(12));

        File::put($restorePath, $payload);

        config()->set('database.connections.'.$connectionName, [
            'driver' => 'sqlite',
            'database' => $restorePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge($connectionName);

        try {
            $integrity = DB::connection($connectionName)->selectOne('PRAGMA integrity_check');
            $status = trim((string) ($integrity->integrity_check ?? ''));
        } finally {
            DB::disconnect($connectionName);
            DB::purge($connectionName);
        }

        if ($status !== 'ok') {
            throw new RuntimeException('SQLite sandbox integrity check khong dat.');
        }

        $result['status'] = 'pass';
        $result['restore_mode'] = 'sqlite-sandbox';
        $result['drill_artifact'] = $restorePath;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function runMysqlRestoreDrill(array $manifest, string $payload, array $result, string $targetPath): array
    {
        $connectionName = (string) ($manifest['connection'] ?? '');
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection) || $connection === []) {
            throw new RuntimeException('Khong tim thay database connection cho restore drill: '.$connectionName);
        }

        $artifactId = (string) ($manifest['artifact_id'] ?? Str::ulid());
        $restorePath = $targetPath.'/restore-drill-'.$artifactId.'.sql';
        File::put($restorePath, $payload);

        $tempDatabase = 'restore_drill_'.Str::lower(Str::random(12));
        $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
        $port = (string) ($connection['port'] ?? 3306);
        $username = trim((string) ($connection['username'] ?? ''));
        $password = (string) ($connection['password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('Thong tin ket noi MySQL khong day du de restore drill.');
        }

        $environment = ['MYSQL_PWD' => $password];

        try {
            $this->runCommand(
                ['mysql', '--host='.$host, '--port='.$port, '--user='.$username, '--execute=CREATE DATABASE `'.$tempDatabase.'`'],
                $environment,
            );
            $this->runCommand(
                ['mysql', '--host='.$host, '--port='.$port, '--user='.$username, $tempDatabase],
                $environment,
                $payload,
            );
            $verify = $this->runCommand(
                ['mysql', '--host='.$host, '--port='.$port, '--user='.$username, $tempDatabase, '--batch', '--silent', '--skip-column-names', '--execute=SHOW TABLES'],
                $environment,
            );
        } finally {
            $this->runCommand(
                ['mysql', '--host='.$host, '--port='.$port, '--user='.$username, '--execute=DROP DATABASE IF EXISTS `'.$tempDatabase.'`'],
                $environment,
                throwOnFailure: false,
            );
        }

        if (trim($verify->output()) === '') {
            throw new RuntimeException('MySQL restore sandbox khong tao duoc bang nao.');
        }

        $result['status'] = 'pass';
        $result['restore_mode'] = 'mysql-sandbox';
        $result['drill_artifact'] = $restorePath;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function runPgsqlRestoreDrill(array $manifest, string $payload, array $result, string $targetPath): array
    {
        $connectionName = (string) ($manifest['connection'] ?? '');
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection) || $connection === []) {
            throw new RuntimeException('Khong tim thay database connection cho restore drill: '.$connectionName);
        }

        $artifactId = (string) ($manifest['artifact_id'] ?? Str::ulid());
        $restorePath = $targetPath.'/restore-drill-'.$artifactId.'.sql';
        File::put($restorePath, $payload);

        $tempDatabase = 'restore_drill_'.Str::lower(Str::random(12));
        $host = trim((string) ($connection['host'] ?? '127.0.0.1'));
        $port = (string) ($connection['port'] ?? 5432);
        $username = trim((string) ($connection['username'] ?? ''));
        $password = (string) ($connection['password'] ?? '');
        $database = trim((string) ($connection['database'] ?? 'postgres'));

        if ($username === '') {
            throw new RuntimeException('Thong tin ket noi PostgreSQL khong day du de restore drill.');
        }

        $environment = ['PGPASSWORD' => $password];

        try {
            $this->runCommand(
                ['psql', '--host='.$host, '--port='.$port, '--username='.$username, '--dbname='.$database, '--set=ON_ERROR_STOP=1', '--command=CREATE DATABASE '.$tempDatabase],
                $environment,
            );
            $this->runCommand(
                ['psql', '--host='.$host, '--port='.$port, '--username='.$username, '--dbname='.$tempDatabase, '--set=ON_ERROR_STOP=1'],
                $environment,
                $payload,
            );
            $verify = $this->runCommand(
                ['psql', '--host='.$host, '--port='.$port, '--username='.$username, '--dbname='.$tempDatabase, '--tuples-only', '--set=ON_ERROR_STOP=1', "--command=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema')"],
                $environment,
            );
        } finally {
            $this->runCommand(
                ['psql', '--host='.$host, '--port='.$port, '--username='.$username, '--dbname='.$database, '--set=ON_ERROR_STOP=1', '--command=DROP DATABASE IF EXISTS '.$tempDatabase],
                $environment,
                throwOnFailure: false,
            );
        }

        if ((int) trim($verify->output()) <= 0) {
            throw new RuntimeException('PostgreSQL restore sandbox khong tao duoc bang nao.');
        }

        $result['status'] = 'pass';
        $result['restore_mode'] = 'pgsql-sandbox';
        $result['drill_artifact'] = $restorePath;

        return $result;
    }

    /**
     * @param  array<string, string>  $environment
     */
    protected function runCommand(array $command, array $environment, ?string $input = null, bool $throwOnFailure = true): ProcessResultContract
    {
        $process = Process::path(base_path())
            ->timeout(600)
            ->env($environment);

        if ($input !== null) {
            $process = $process->input($input);
        }

        $result = $process->run($command);

        if ($throwOnFailure && ! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) !== '' ? trim($result->errorOutput()) : 'Restore sandbox command that bai.');
        }

        return $result;
    }
}
