<?php

namespace App\Console\Commands;

use App\Models\PatientPhoto;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrunePatientPhotos extends Command
{
    protected $signature = 'photos:prune
        {--days= : Số ngày retention override runtime setting}
        {--include-xray : Áp dụng dọn cả ảnh X-quang}
        {--dry-run : Chỉ thống kê, không xóa dữ liệu}';

    protected $description = 'Dọn ảnh bệnh nhân quá hạn retention policy.';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption)
            ? max(0, (int) $daysOption)
            : ClinicRuntimeSettings::patientPhotoRetentionDays();

        $runtimeEnabled = ClinicRuntimeSettings::patientPhotoRetentionEnabled();
        if (! $runtimeEnabled && ! is_numeric($daysOption)) {
            $this->info('Retention ảnh đang tắt. Bỏ qua.');

            return self::SUCCESS;
        }

        if ($days <= 0) {
            $this->info('Retention days <= 0. Không có ảnh cần dọn.');

            return self::SUCCESS;
        }

        $includeXray = (bool) $this->option('include-xray') || ClinicRuntimeSettings::patientPhotoRetentionIncludeXray();
        $types = ['normal', 'ext', 'int'];

        if ($includeXray) {
            $types[] = 'xray';
        }

        $cutoff = now()->subDays($days)->startOfDay();

        $query = PatientPhoto::query()
            ->whereIn('type', $types)
            ->whereDate('date', '<', $cutoff->toDateString());

        $candidateCount = (clone $query)->count();
        if ($candidateCount === 0) {
            $this->info('Không có ảnh nào quá hạn retention.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Ảnh ứng viên: %d | cutoff: %s | include_xray=%s',
            $candidateCount,
            $cutoff->toDateString(),
            $includeXray ? 'yes' : 'no',
        ));

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry-run mode: không xóa dữ liệu.');

            return self::SUCCESS;
        }

        $disk = Storage::disk((string) config('filament.default_filesystem_disk', 'public'));
        $deletedPhotos = 0;
        $deletedFiles = 0;

        $query->orderBy('id')->chunkById(200, function ($photos) use ($disk, &$deletedFiles, &$deletedPhotos): void {
            foreach ($photos as $photo) {
                $paths = $this->resolvePhotoPaths($photo->content);

                foreach ($paths as $path) {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                        $deletedFiles++;
                    }
                }

                $photo->delete();
                $deletedPhotos++;
            }
        });

        $this->info(sprintf(
            'Hoàn tất dọn ảnh: deleted_photos=%d, deleted_files=%d',
            $deletedPhotos,
            $deletedFiles,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePhotoPaths(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }

        return collect($content)
            ->flatMap(function (mixed $value): array {
                if (is_array($value)) {
                    return collect($value)
                        ->filter(static fn (mixed $nested): bool => is_string($nested) && trim($nested) !== '')
                        ->map(static fn (string $nested): string => trim($nested))
                        ->values()
                        ->all();
                }

                if (is_string($value) && trim($value) !== '') {
                    return [trim($value)];
                }

                return [];
            })
            ->unique()
            ->values()
            ->all();
    }
}
