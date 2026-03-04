<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\DicomReadinessService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CheckDicomReadiness extends Command
{
    protected $signature = 'emr:check-dicom-readiness
        {--probe : Thử gọi endpoint /health của DICOM base URL}
        {--strict : Fail khi readiness chưa đạt (chỉ áp dụng khi module DICOM bật)}
        {--export= : Đường dẫn JSON output}';

    protected $description = 'Kiểm tra DICOM readiness (optional) cho EMR imaging integration.';

    public function __construct(protected DicomReadinessService $readinessService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy kiểm tra DICOM readiness.',
        );

        $probe = (bool) $this->option('probe');
        $strict = (bool) $this->option('strict');

        $snapshot = $this->readinessService->snapshot($probe);

        $this->line('DICOM_ENABLED: '.($snapshot['enabled'] ? 'yes' : 'no'));
        $this->line('DICOM_READY: '.($snapshot['ready'] ? 'yes' : 'no'));

        $this->table(
            ['Check', 'Passed', 'Message'],
            collect($snapshot['checks'])
                ->map(fn (array $check): array => [
                    (string) $check['code'],
                    $check['passed'] ? 'yes' : 'no',
                    (string) $check['message'],
                ])
                ->values()
                ->all(),
        );

        $exportPath = $this->resolveExportPath();
        if ($exportPath !== null) {
            File::ensureDirectoryExists(dirname($exportPath));
            File::put(
                $exportPath,
                json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
            $this->line('DICOM_READINESS_REPORT: '.$exportPath);
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $snapshot['ready'] ? AuditLog::ACTION_RUN : AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: [
                'command' => 'emr:check-dicom-readiness',
                'probe' => $probe,
                'strict' => $strict,
                'snapshot' => $snapshot,
                'export_path' => $exportPath,
            ],
        );

        if ($strict && $snapshot['enabled'] && ! $snapshot['ready']) {
            $this->error('DICOM readiness chưa đạt trong strict mode.');

            return self::FAILURE;
        }

        if (! $snapshot['enabled']) {
            $this->info('DICOM module đang tắt; readiness check chỉ mang tính thông tin.');
        } elseif ($snapshot['ready']) {
            $this->info('DICOM readiness đạt yêu cầu.');
        } else {
            $this->warn('DICOM readiness chưa đạt, vui lòng bổ sung cấu hình.');
        }

        return self::SUCCESS;
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
}
