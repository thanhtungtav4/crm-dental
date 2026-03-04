<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReconcileClinicalMediaAssets extends Command
{
    protected $signature = 'emr:reconcile-clinical-media
        {--strict : Trả mã lỗi nếu phát hiện mismatch}
        {--export= : Đường dẫn xuất JSON report}
        {--sample-limit=20 : Số lượng sample id trả về cho mỗi check}';

    protected $description = 'Đối soát toàn vẹn clinical media (linkage/version/checksum/retention).';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy đối soát clinical media.',
        );

        $strict = (bool) $this->option('strict');
        $sampleLimit = max(1, min(100, (int) $this->option('sample-limit')));
        $report = $this->buildReport($sampleLimit);

        $rows = collect($report['checks'])
            ->map(fn (array $check): array => [
                $check['code'],
                (int) $check['count'],
                implode(',', (array) $check['sample_ids']),
            ])
            ->values()
            ->all();

        $this->table(
            ['Check', 'Count', 'Sample IDs'],
            $rows,
        );

        $totalIssues = (int) $report['summary']['total_issues'];
        $exportPath = $this->resolveExportPath();

        if ($exportPath !== null) {
            $this->writeReport($exportPath, $report);
            $this->line('CLINICAL_MEDIA_RECONCILE_REPORT: '.$exportPath);
        }

        $metadata = [
            'command' => 'emr:reconcile-clinical-media',
            'strict' => $strict,
            'summary' => $report['summary'],
            'checks' => $report['checks'],
            'export_path' => $exportPath,
        ];

        if ($totalIssues > 0) {
            $this->warn("Clinical media reconcile phát hiện {$totalIssues} vấn đề.");

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_FAIL,
                actorId: auth()->id(),
                metadata: $metadata,
            );

            return $strict ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Clinical media reconcile không phát hiện mismatch.');

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: $metadata,
        );

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     checks:array<int, array{code:string,count:int,sample_ids:array<int, int>}>,
     *     summary:array{total_checks:int,total_issues:int,checked_at:string}
     * }
     */
    protected function buildReport(int $sampleLimit): array
    {
        $checks = [];

        $checks[] = $this->runSimpleCheck(
            code: 'missing_patient_link',
            query: ClinicalMediaAsset::query()->whereNull('patient_id'),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'missing_branch_link',
            query: ClinicalMediaAsset::query()->whereNull('branch_id'),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'missing_storage_path_asset',
            query: ClinicalMediaAsset::query()->where(function (\Illuminate\Database\Eloquent\Builder $query): void {
                $query->whereNull('storage_path')->orWhere('storage_path', '');
            }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'missing_checksum_asset',
            query: ClinicalMediaAsset::query()->where(function (\Illuminate\Database\Eloquent\Builder $query): void {
                $query->whereNull('checksum_sha256')->orWhere('checksum_sha256', '');
            }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'asset_missing_original_version',
            query: ClinicalMediaAsset::query()
                ->whereNotExists(function (QueryBuilder $query): void {
                    $query->select(DB::raw(1))
                        ->from('clinical_media_versions')
                        ->whereColumn('clinical_media_versions.clinical_media_asset_id', 'clinical_media_assets.id')
                        ->where('clinical_media_versions.is_original', true);
                }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'asset_multiple_original_versions',
            query: ClinicalMediaAsset::query()
                ->whereIn('id', function (QueryBuilder $query): void {
                    $query->from('clinical_media_versions')
                        ->select('clinical_media_asset_id')
                        ->where('is_original', true)
                        ->groupBy('clinical_media_asset_id')
                        ->havingRaw('COUNT(*) > 1');
                }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'missing_storage_path_version',
            query: ClinicalMediaVersion::query()->where(function (\Illuminate\Database\Eloquent\Builder $query): void {
                $query->whereNull('storage_path')->orWhere('storage_path', '');
            }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'missing_checksum_version',
            query: ClinicalMediaVersion::query()->where(function (\Illuminate\Database\Eloquent\Builder $query): void {
                $query->whereNull('checksum_sha256')->orWhere('checksum_sha256', '');
            }),
            sampleLimit: $sampleLimit,
        );
        $checks[] = $this->runSimpleCheck(
            code: 'deleted_asset_not_archived_status',
            query: ClinicalMediaAsset::onlyTrashed()->where('status', '!=', ClinicalMediaAsset::STATUS_ARCHIVED),
            sampleLimit: $sampleLimit,
        );

        $totalIssues = collect($checks)->sum('count');

        return [
            'checks' => $checks,
            'summary' => [
                'total_checks' => count($checks),
                'total_issues' => (int) $totalIssues,
                'checked_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array{code:string,count:int,sample_ids:array<int, int>}
     */
    protected function runSimpleCheck(string $code, \Illuminate\Database\Eloquent\Builder $query, int $sampleLimit): array
    {
        $count = (int) (clone $query)->count();
        $sampleIds = (clone $query)
            ->limit($sampleLimit)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return [
            'code' => $code,
            'count' => $count,
            'sample_ids' => $sampleIds,
        ];
    }

    protected function resolveExportPath(): ?string
    {
        $option = trim((string) ($this->option('export') ?? ''));

        if ($option === '') {
            return null;
        }

        if (Str::startsWith($option, ['/'])) {
            return $option;
        }

        return storage_path('app/'.$option);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function writeReport(string $path, array $report): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
