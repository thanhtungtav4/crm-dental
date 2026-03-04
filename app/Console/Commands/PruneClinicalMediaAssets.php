<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneClinicalMediaAssets extends Command
{
    protected $signature = 'emr:prune-clinical-media
        {--class=* : Retention class cần dọn (clinical_operational|temporary)}
        {--days= : Override retention days cho class được chọn}
        {--dry-run : Chỉ thống kê, không xóa}
        {--strict : Trả mã lỗi nếu có lỗi runtime trong quá trình dọn}';

    protected $description = 'Dọn clinical media theo retention class-aware (không xóa legal hold/clinical_legal).';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy retention clinical media.',
        );

        $daysOverride = $this->resolveDaysOverride();
        $strict = (bool) $this->option('strict');
        $dryRun = (bool) $this->option('dry-run');
        $runtimeEnabled = ClinicRuntimeSettings::clinicalMediaRetentionEnabled();
        $selectedClasses = $this->resolveRetentionClasses();

        if (! $runtimeEnabled && $daysOverride === null && $selectedClasses === []) {
            $this->info('Retention clinical media đang tắt. Bỏ qua.');

            return self::SUCCESS;
        }

        if ($selectedClasses === []) {
            $selectedClasses = [
                ClinicalMediaAsset::RETENTION_TEMPORARY,
                ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            ];
        }

        $rows = [];
        $summary = [
            'candidates' => 0,
            'processed_assets' => 0,
            'deleted_files' => 0,
            'errors' => 0,
            'classes' => $selectedClasses,
            'days_override' => $daysOverride,
            'dry_run' => $dryRun,
        ];

        foreach ($selectedClasses as $retentionClass) {
            $days = $daysOverride ?? ClinicRuntimeSettings::clinicalMediaRetentionDays($retentionClass);
            if ($days <= 0) {
                $rows[] = [$retentionClass, $days, '-', 0, 'SKIP (days<=0)'];

                continue;
            }

            $cutoff = now()->subDays($days);
            $query = ClinicalMediaAsset::query()
                ->whereNull('deleted_at')
                ->where('status', ClinicalMediaAsset::STATUS_ACTIVE)
                ->where('legal_hold', false)
                ->where('retention_class', $retentionClass)
                ->where('captured_at', '<=', $cutoff);

            $candidateCount = (int) (clone $query)->count();
            $summary['candidates'] += $candidateCount;

            $processedCount = 0;
            $deletedFilesCount = 0;
            $errorsCount = 0;

            if (! $dryRun && $candidateCount > 0) {
                (clone $query)->orderBy('id')->chunkById(100, function (Collection $assets) use (
                    &$processedCount,
                    &$deletedFilesCount,
                    &$errorsCount
                ): void {
                    foreach ($assets as $asset) {
                        try {
                            $deletedFilesCount += $this->deleteAssetBinaryFiles($asset);
                            $asset->delete();
                            $processedCount++;
                        } catch (Throwable $throwable) {
                            $errorsCount++;
                            $this->warn(sprintf(
                                'Không thể dọn asset #%d: %s',
                                (int) $asset->id,
                                $throwable->getMessage(),
                            ));
                        }
                    }
                });
            }

            $summary['processed_assets'] += $processedCount;
            $summary['deleted_files'] += $deletedFilesCount;
            $summary['errors'] += $errorsCount;

            $rows[] = [
                $retentionClass,
                $days,
                $cutoff->toDateTimeString(),
                $candidateCount,
                $dryRun ? 'DRY_RUN' : sprintf('processed=%d, files=%d, errors=%d', $processedCount, $deletedFilesCount, $errorsCount),
            ];
        }

        $this->table(
            ['Retention Class', 'Days', 'Cutoff', 'Candidates', 'Result'],
            $rows,
        );

        $metadata = [
            'command' => 'emr:prune-clinical-media',
            'summary' => $summary,
            'rows' => $rows,
        ];

        if ($summary['errors'] > 0) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                metadata: $metadata,
            );

            if ($strict) {
                $this->error('Có lỗi khi dọn clinical media và đang bật --strict.');

                return self::FAILURE;
            }
        } else {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: $metadata,
            );
        }

        $this->info(sprintf(
            'Hoàn tất prune clinical media: candidates=%d, processed=%d, deleted_files=%d, errors=%d, dry_run=%s',
            $summary['candidates'],
            $summary['processed_assets'],
            $summary['deleted_files'],
            $summary['errors'],
            $dryRun ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }

    protected function resolveDaysOverride(): ?int
    {
        $option = $this->option('days');

        if ($option === null || $option === '') {
            return null;
        }

        if (! is_numeric($option)) {
            $this->warn('Giá trị --days không hợp lệ, bỏ qua override.');

            return null;
        }

        return max(0, (int) $option);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveRetentionClasses(): array
    {
        $raw = $this->option('class');
        $values = is_array($raw) ? $raw : [];

        $allowed = [
            ClinicalMediaAsset::RETENTION_TEMPORARY,
            ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
        ];

        return collect($values)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => strtolower(trim($item)))
            ->intersect($allowed)
            ->unique()
            ->values()
            ->all();
    }

    protected function deleteAssetBinaryFiles(ClinicalMediaAsset $asset): int
    {
        $paths = collect([
            [
                'disk' => (string) $asset->storage_disk,
                'path' => (string) $asset->storage_path,
            ],
        ])->merge(
            $asset->versions()
                ->get(['storage_disk', 'storage_path'])
                ->map(fn (ClinicalMediaVersion $version): array => [
                    'disk' => (string) $version->storage_disk,
                    'path' => (string) $version->storage_path,
                ]),
        )
            ->filter(fn (array $entry): bool => trim((string) ($entry['path'] ?? '')) !== '')
            ->unique(fn (array $entry): string => ($entry['disk'] ?? '').'|'.($entry['path'] ?? ''))
            ->values();

        $deletedFiles = 0;

        foreach ($paths as $entry) {
            $diskName = (string) ($entry['disk'] ?? '');
            $path = trim((string) ($entry['path'] ?? ''));

            if ($diskName === '' || $path === '') {
                continue;
            }

            $disk = Storage::disk($diskName);
            if ($disk->exists($path)) {
                $disk->delete($path);
                $deletedFiles++;
            }
        }

        return $deletedFiles;
    }
}
