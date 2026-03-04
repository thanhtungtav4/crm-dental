<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\ClinicalNote;
use App\Models\PatientPhoto;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackfillClinicalMediaAssets extends Command
{
    protected $signature = 'emr:backfill-clinical-media
        {--chunk=200 : Kích thước chunk khi scan dữ liệu legacy}
        {--dry-run : Chỉ thống kê, không ghi dữ liệu}
        {--strict : Fail command nếu có lỗi runtime trong quá trình backfill}';

    protected $description = 'Backfill dữ liệu ảnh legacy sang clinical_media_assets (idempotent).';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy backfill clinical media.',
        );

        $chunk = max(50, min(1000, (int) $this->option('chunk')));
        $dryRun = (bool) $this->option('dry-run');
        $strict = (bool) $this->option('strict');

        $summary = [
            'patient_photos' => $this->backfillPatientPhotos($chunk, $dryRun),
            'clinical_note_indications' => $this->backfillClinicalNoteIndicationImages($chunk, $dryRun),
            'dry_run' => $dryRun,
            'chunk' => $chunk,
        ];

        $rows = [
            [
                'patient_photos',
                $summary['patient_photos']['rows_scanned'],
                $summary['patient_photos']['paths_scanned'],
                $summary['patient_photos']['created'],
                $summary['patient_photos']['deduped'],
                $summary['patient_photos']['errors'],
            ],
            [
                'clinical_note_indications',
                $summary['clinical_note_indications']['rows_scanned'],
                $summary['clinical_note_indications']['paths_scanned'],
                $summary['clinical_note_indications']['created'],
                $summary['clinical_note_indications']['deduped'],
                $summary['clinical_note_indications']['errors'],
            ],
        ];

        $this->table(
            ['Source', 'Rows Scanned', 'Paths Scanned', 'Created', 'Deduped', 'Errors'],
            $rows,
        );

        $totalErrors = (int) $summary['patient_photos']['errors'] + (int) $summary['clinical_note_indications']['errors'];

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $totalErrors > 0 ? AuditLog::ACTION_FAIL : AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: [
                'command' => 'emr:backfill-clinical-media',
                'summary' => $summary,
            ],
        );

        if ($strict && $totalErrors > 0) {
            $this->error('Backfill phát sinh lỗi và đang bật --strict.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backfill clinical media hoàn tất: created=%d, deduped=%d, errors=%d, dry_run=%s',
            (int) $summary['patient_photos']['created'] + (int) $summary['clinical_note_indications']['created'],
            (int) $summary['patient_photos']['deduped'] + (int) $summary['clinical_note_indications']['deduped'],
            $totalErrors,
            $dryRun ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{rows_scanned:int,paths_scanned:int,created:int,deduped:int,errors:int}
     */
    protected function backfillPatientPhotos(int $chunk, bool $dryRun): array
    {
        $stats = [
            'rows_scanned' => 0,
            'paths_scanned' => 0,
            'created' => 0,
            'deduped' => 0,
            'errors' => 0,
        ];

        PatientPhoto::query()
            ->with('patient:id,first_branch_id')
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $photos) use (&$stats, $dryRun): void {
                foreach ($photos as $photo) {
                    $stats['rows_scanned']++;
                    $paths = $this->extractLegacyPaths($photo->content);
                    $stats['paths_scanned'] += count($paths);

                    foreach ($paths as $path) {
                        try {
                            $payload = $this->buildAssetPayloadFromPatientPhoto($photo, $path);
                            $result = $this->createOrReuseAsset($payload, $dryRun);
                            $stats[$result]++;
                        } catch (Throwable $throwable) {
                            $stats['errors']++;
                            $this->warn(sprintf(
                                'patient_photos#%d (%s) lỗi: %s',
                                (int) $photo->id,
                                $path,
                                $throwable->getMessage(),
                            ));
                        }
                    }
                }
            });

        return $stats;
    }

    /**
     * @return array{rows_scanned:int,paths_scanned:int,created:int,deduped:int,errors:int}
     */
    protected function backfillClinicalNoteIndicationImages(int $chunk, bool $dryRun): array
    {
        $stats = [
            'rows_scanned' => 0,
            'paths_scanned' => 0,
            'created' => 0,
            'deduped' => 0,
            'errors' => 0,
        ];

        ClinicalNote::query()
            ->whereNotNull('indication_images')
            ->with('patient:id,first_branch_id')
            ->orderBy('id')
            ->chunkById($chunk, function (Collection $notes) use (&$stats, $dryRun): void {
                foreach ($notes as $note) {
                    $stats['rows_scanned']++;
                    $indicationImages = is_array($note->indication_images) ? $note->indication_images : [];

                    foreach ($indicationImages as $indicationType => $rawPaths) {
                        $paths = $this->extractLegacyPaths($rawPaths);
                        $stats['paths_scanned'] += count($paths);

                        foreach ($paths as $path) {
                            try {
                                $payload = $this->buildAssetPayloadFromClinicalNote($note, (string) $indicationType, $path);
                                $result = $this->createOrReuseAsset($payload, $dryRun);
                                $stats[$result]++;
                            } catch (Throwable $throwable) {
                                $stats['errors']++;
                                $this->warn(sprintf(
                                    'clinical_notes#%d (%s/%s) lỗi: %s',
                                    (int) $note->id,
                                    (string) $indicationType,
                                    $path,
                                    $throwable->getMessage(),
                                ));
                            }
                        }
                    }
                }
            });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function createOrReuseAsset(array $payload, bool $dryRun): string
    {
        $assetQuery = ClinicalMediaAsset::query()
            ->where('patient_id', (int) $payload['patient_id'])
            ->where('storage_disk', (string) $payload['storage_disk'])
            ->where('storage_path', (string) $payload['storage_path']);

        if (! empty($payload['exam_session_id'])) {
            $assetQuery->where('exam_session_id', (int) $payload['exam_session_id']);
        }

        /** @var ClinicalMediaAsset|null $existing */
        $existing = $assetQuery->first();
        if ($existing instanceof ClinicalMediaAsset) {
            return 'deduped';
        }

        if ($dryRun) {
            return 'created';
        }

        DB::transaction(function () use ($payload): void {
            $asset = ClinicalMediaAsset::query()->create($payload);

            ClinicalMediaVersion::query()->create([
                'clinical_media_asset_id' => (int) $asset->id,
                'version_number' => 1,
                'is_original' => true,
                'mime_type' => $asset->mime_type,
                'file_size_bytes' => $asset->file_size_bytes,
                'checksum_sha256' => $asset->checksum_sha256,
                'storage_disk' => $asset->storage_disk,
                'storage_path' => $asset->storage_path,
                'created_by' => $asset->captured_by,
            ]);
        }, 3);

        return 'created';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAssetPayloadFromPatientPhoto(PatientPhoto $photo, string $path): array
    {
        $normalizedPath = $this->normalizeStoragePath($path);
        $disk = $this->resolveDiskForPath($normalizedPath);
        $checksum = $this->resolveChecksum($disk, $normalizedPath);
        $fileSize = $this->resolveFileSize($disk, $normalizedPath);

        $legacyType = strtolower(trim((string) ($photo->type ?? 'normal')));
        $modality = $legacyType === PatientPhoto::TYPE_XRAY
            ? ClinicalMediaAsset::MODALITY_XRAY
            : ClinicalMediaAsset::MODALITY_PHOTO;

        $anatomyScope = match ($legacyType) {
            PatientPhoto::TYPE_INTERNAL => 'intraoral',
            PatientPhoto::TYPE_EXTERNAL => 'extraoral',
            PatientPhoto::TYPE_XRAY => 'radiology',
            default => 'general',
        };

        return [
            'patient_id' => (int) $photo->patient_id,
            'branch_id' => $photo->patient?->first_branch_id ? (int) $photo->patient->first_branch_id : null,
            'captured_by' => auth()->id(),
            'captured_at' => $photo->date?->copy()->setTime(9, 0, 0) ?? $photo->created_at ?? now(),
            'modality' => $modality,
            'anatomy_scope' => $anatomyScope,
            'phase' => 'baseline',
            'mime_type' => $this->guessMimeType($normalizedPath),
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksum,
            'storage_disk' => $disk,
            'storage_path' => $normalizedPath,
            'status' => ClinicalMediaAsset::STATUS_ACTIVE,
            'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            'legal_hold' => false,
            'meta' => [
                'source_table' => 'patient_photos',
                'source_id' => (int) $photo->id,
                'legacy_type' => $legacyType,
                'legacy_title' => $photo->title,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAssetPayloadFromClinicalNote(ClinicalNote $note, string $indicationType, string $path): array
    {
        $normalizedPath = $this->normalizeStoragePath($path);
        $disk = $this->resolveDiskForPath($normalizedPath);
        $checksum = $this->resolveChecksum($disk, $normalizedPath);
        $fileSize = $this->resolveFileSize($disk, $normalizedPath);
        $normalizedType = strtolower(trim($indicationType));

        $modality = Str::contains($normalizedType, ['xray', 'panorama', 'cephalometric', '3d'])
            ? ClinicalMediaAsset::MODALITY_XRAY
            : ClinicalMediaAsset::MODALITY_PHOTO;

        $anatomyScope = match ($normalizedType) {
            'int' => 'intraoral',
            'ext' => 'extraoral',
            default => $normalizedType !== '' ? $normalizedType : 'general',
        };

        return [
            'patient_id' => (int) $note->patient_id,
            'visit_episode_id' => $note->visit_episode_id ? (int) $note->visit_episode_id : null,
            'exam_session_id' => $note->exam_session_id ? (int) $note->exam_session_id : null,
            'branch_id' => $note->branch_id
                ? (int) $note->branch_id
                : ($note->patient?->first_branch_id ? (int) $note->patient->first_branch_id : null),
            'captured_by' => $note->updated_by ?: $note->doctor_id ?: auth()->id(),
            'captured_at' => $note->updated_at ?? $note->created_at ?? now(),
            'modality' => $modality,
            'anatomy_scope' => $anatomyScope,
            'phase' => 'pre',
            'mime_type' => $this->guessMimeType($normalizedPath),
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksum,
            'storage_disk' => $disk,
            'storage_path' => $normalizedPath,
            'status' => ClinicalMediaAsset::STATUS_ACTIVE,
            'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            'legal_hold' => false,
            'meta' => [
                'source_table' => 'clinical_notes.indication_images',
                'source_id' => (int) $note->id,
                'indication_type' => $normalizedType,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function extractLegacyPaths(mixed $rawPaths): array
    {
        if (is_string($rawPaths)) {
            $rawPaths = [$rawPaths];
        }

        if (! is_array($rawPaths)) {
            return [];
        }

        return collect($rawPaths)
            ->flatMap(function (mixed $value): array {
                if (is_array($value)) {
                    return collect($value)
                        ->filter(fn (mixed $nested): bool => is_string($nested) && trim($nested) !== '')
                        ->map(fn (string $nested): string => trim($nested))
                        ->values()
                        ->all();
                }

                if (is_string($value) && trim($value) !== '') {
                    return [trim($value)];
                }

                return [];
            })
            ->map(fn (string $path): string => $this->normalizeStoragePath($path))
            ->filter(fn (string $path): bool => $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeStoragePath(string $path): string
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return '';
        }

        if (Str::startsWith($normalized, ['http://', 'https://'])) {
            $parsedPath = parse_url($normalized, PHP_URL_PATH);
            $normalized = is_string($parsedPath) ? $parsedPath : $normalized;
        }

        $normalized = ltrim($normalized, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return trim($normalized);
    }

    protected function resolveDiskForPath(string $path): string
    {
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            return 'public';
        }

        return (string) config('filesystems.default', 'local');
    }

    protected function resolveChecksum(string $disk, string $path): string
    {
        if ($path === '') {
            return hash('sha256', Str::uuid()->toString());
        }

        if (Storage::disk($disk)->exists($path)) {
            $stream = Storage::disk($disk)->readStream($path);
            if (is_resource($stream)) {
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);

                return hash_final($hash);
            }
        }

        return hash('sha256', $disk.'|'.$path);
    }

    protected function resolveFileSize(string $disk, string $path): ?int
    {
        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $size = Storage::disk($disk)->size($path);

        return is_int($size) ? $size : null;
    }

    protected function guessMimeType(string $path): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => null,
        };
    }
}
