<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class BackupArtifactManifestService
{
    public const FORMAT_VERSION = 1;

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        return [
            'format_version' => self::FORMAT_VERSION,
            'artifact_id' => (string) ($manifest['artifact_id'] ?? Str::ulid()),
            'created_at' => (string) ($manifest['created_at'] ?? now()->toIso8601String()),
            'connection' => (string) ($manifest['connection'] ?? ''),
            'driver' => (string) ($manifest['driver'] ?? ''),
            'artifact_file' => (string) ($manifest['artifact_file'] ?? ''),
            'artifact_size_bytes' => (int) ($manifest['artifact_size_bytes'] ?? 0),
            'artifact_checksum_sha256' => (string) ($manifest['artifact_checksum_sha256'] ?? ''),
            'plaintext_size_bytes' => (int) ($manifest['plaintext_size_bytes'] ?? 0),
            'plaintext_checksum_sha256' => (string) ($manifest['plaintext_checksum_sha256'] ?? ''),
            'encrypted' => (bool) ($manifest['encrypted'] ?? false),
            'encryption_driver' => (string) ($manifest['encryption_driver'] ?? ''),
            'payload_encoding' => (string) ($manifest['payload_encoding'] ?? ''),
            'source_format' => (string) ($manifest['source_format'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function write(string $backupPath, array $manifest): string
    {
        File::ensureDirectoryExists($backupPath);

        $normalized = $this->normalize($manifest);
        $manifestPath = $backupPath.'/'.$this->manifestFilename($normalized);

        File::put(
            $manifestPath,
            json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        return $manifestPath;
    }

    public function latest(string $backupPath): ?array
    {
        if (! is_dir($backupPath)) {
            return null;
        }

        $manifest = collect(File::files($backupPath))
            ->filter(fn (\SplFileInfo $file): bool => $this->isManifestFile($file))
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->first();

        if (! $manifest instanceof \SplFileInfo) {
            return null;
        }

        $decoded = $this->decode($manifest->getPathname());

        if ($decoded === null) {
            return [
                'path' => $manifest->getPathname(),
                'data' => null,
            ];
        }

        return [
            'path' => $manifest->getPathname(),
            'data' => $decoded,
        ];
    }

    public function decode(string $manifestPath): ?array
    {
        if (! File::exists($manifestPath)) {
            return null;
        }

        try {
            $decoded = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $manifest
     * @return list<string>
     */
    public function validate(?array $manifest): array
    {
        if ($manifest === null) {
            return ['manifest_invalid'];
        }

        $errors = [];

        if ((int) ($manifest['format_version'] ?? 0) !== self::FORMAT_VERSION) {
            $errors[] = 'invalid_format_version';
        }

        foreach (['artifact_id', 'created_at', 'connection', 'driver', 'artifact_file', 'artifact_checksum_sha256', 'plaintext_checksum_sha256', 'encryption_driver', 'payload_encoding', 'source_format'] as $key) {
            if (! is_string($manifest[$key] ?? null) || trim((string) $manifest[$key]) === '') {
                $errors[] = 'missing_'.$key;
            }
        }

        if (! is_int($manifest['artifact_size_bytes'] ?? null) && ! is_numeric($manifest['artifact_size_bytes'] ?? null)) {
            $errors[] = 'invalid_artifact_size_bytes';
        }

        if (! is_int($manifest['plaintext_size_bytes'] ?? null) && ! is_numeric($manifest['plaintext_size_bytes'] ?? null)) {
            $errors[] = 'invalid_plaintext_size_bytes';
        }

        if (($manifest['encrypted'] ?? null) !== true) {
            $errors[] = 'artifact_not_encrypted';
        }

        if ($this->createdAt($manifest) === null) {
            $errors[] = 'invalid_created_at';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function artifactPath(string $backupPath, array $manifest): ?string
    {
        $artifactFile = $manifest['artifact_file'] ?? null;

        if (! is_string($artifactFile) || trim($artifactFile) === '') {
            return null;
        }

        return $backupPath.'/'.$artifactFile;
    }

    /**
     * @param  array<string, mixed>|null  $manifest
     */
    public function createdAt(?array $manifest): ?CarbonImmutable
    {
        $createdAt = $manifest['created_at'] ?? null;

        if (! is_string($createdAt) || trim($createdAt) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($createdAt);
        } catch (Throwable) {
            return null;
        }
    }

    public function checksum(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $checksum = hash_file('sha256', $path);

        return is_string($checksum) && $checksum !== '' ? $checksum : null;
    }

    public function isManifestFile(\SplFileInfo $file): bool
    {
        return Str::endsWith($file->getFilename(), '.manifest.json');
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function manifestFilename(array $manifest): string
    {
        return 'crm-backup-'.$manifest['connection'].'-'.$manifest['artifact_id'].'.manifest.json';
    }
}
