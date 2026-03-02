<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class VerifyProductionReadinessReport extends Command
{
    protected $signature = 'ops:verify-production-readiness-report
        {report : Duong dan report JSON tu ops:run-production-readiness}
        {--qa= : Nguoi ky QA (email/username/ho ten)}
        {--pm= : Nguoi ky PM (email/username/ho ten)}
        {--release-ref= : Ma release/ticket de truy vet}
        {--strict : Bat buoc report dat full gate (status pass, strict_full, with_finance, run_tests)}
        {--output= : Duong dan signed checklist artifact JSON}';

    protected $description = 'Validate schema report readiness va ky checklist QA/PM truoc deploy.';

    public function handle(): int
    {
        $inputPath = (string) $this->argument('report');
        $reportPath = $this->resolveReportPath($inputPath);

        if ($reportPath === null || ! is_file($reportPath)) {
            $this->error('READINESS_REPORT_STATUS: FAIL');
            $this->error('Khong tim thay report JSON: '.$inputPath);

            return self::FAILURE;
        }

        $raw = file_get_contents($reportPath);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded)) {
            $this->error('READINESS_REPORT_STATUS: FAIL');
            $this->error('Report JSON khong hop le: '.$reportPath);

            $this->recordAudit(
                status: 'fail',
                reportPath: $reportPath,
                issues: ['invalid_json'],
                signoffPath: null,
            );

            return self::FAILURE;
        }

        $validator = Validator::make($decoded, $this->schemaRules());
        if ($validator->fails()) {
            $this->error('READINESS_REPORT_STATUS: FAIL');
            $this->error('Schema report khong hop le.');

            foreach ($validator->errors()->all() as $message) {
                $this->line('- '.$message);
            }

            $this->recordAudit(
                status: 'fail',
                reportPath: $reportPath,
                issues: $validator->errors()->all(),
                signoffPath: null,
            );

            return self::FAILURE;
        }

        $issues = $this->collectBusinessIssues($decoded);

        if ((bool) $this->option('strict')) {
            if (! (bool) ($decoded['strict_full'] ?? false)) {
                $issues[] = 'strict_full_phai_true_khi_xac_nhan_deploy';
            }

            if (! (bool) ($decoded['with_finance'] ?? false)) {
                $issues[] = 'with_finance_phai_true_khi_xac_nhan_deploy';
            }

            if (! (bool) ($decoded['run_tests'] ?? false)) {
                $issues[] = 'run_tests_phai_true_khi_xac_nhan_deploy';
            }
        }

        $qaSigner = trim((string) ($this->option('qa') ?? ''));
        $pmSigner = trim((string) ($this->option('pm') ?? ''));
        $releaseRef = trim((string) ($this->option('release-ref') ?? ''));

        if ($qaSigner === '') {
            $issues[] = 'thieu_nguoi_ky_qa';
        }

        if ($pmSigner === '') {
            $issues[] = 'thieu_nguoi_ky_pm';
        }

        if ((bool) $this->option('strict') && $releaseRef === '') {
            $issues[] = 'thieu_release_ref_khi_strict';
        }

        if ($issues !== []) {
            $this->error('READINESS_REPORT_STATUS: FAIL');
            $this->error('Checklist sign-off khong dat:');

            foreach ($issues as $issue) {
                $this->line('- '.$issue);
            }

            $this->recordAudit(
                status: 'fail',
                reportPath: $reportPath,
                issues: $issues,
                signoffPath: null,
            );

            return self::FAILURE;
        }

        $outputPath = (string) ($this->option('output') ?: $this->defaultOutputPath($reportPath));
        $resolvedOutputPath = $this->resolveOutputPath($outputPath);

        $signoff = [
            'verified_at' => now()->toDateTimeString(),
            'verified_by_user_id' => auth()->id(),
            'strict_mode' => (bool) $this->option('strict'),
            'report_path' => $reportPath,
            'report_sha256' => hash_file('sha256', $reportPath) ?: null,
            'report_status' => (string) ($decoded['status'] ?? 'unknown'),
            'qa_signoff' => [
                'signer' => $qaSigner,
                'signed_at' => now()->toDateTimeString(),
            ],
            'pm_signoff' => [
                'signer' => $pmSigner,
                'signed_at' => now()->toDateTimeString(),
            ],
            'release_ref' => $releaseRef !== '' ? $releaseRef : null,
            'summary' => [
                'steps_plan_count' => count((array) ($decoded['steps_plan'] ?? [])),
                'steps_run_count' => count((array) ($decoded['steps_run'] ?? [])),
                'duration_ms' => (int) ($decoded['duration_ms'] ?? 0),
                'with_finance' => (bool) ($decoded['with_finance'] ?? false),
                'run_tests' => (bool) ($decoded['run_tests'] ?? false),
                'strict_full' => (bool) ($decoded['strict_full'] ?? false),
            ],
        ];

        $directory = dirname($resolvedOutputPath);
        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put(
            $resolvedOutputPath,
            json_encode($signoff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->info('READINESS_REPORT_STATUS: PASS');
        $this->line('READINESS_REPORT_PATH: '.$reportPath);
        $this->line('READINESS_SIGNOFF_PATH: '.$resolvedOutputPath);
        $this->line('READINESS_SIGNOFF_QA: '.$qaSigner);
        $this->line('READINESS_SIGNOFF_PM: '.$pmSigner);
        $this->line('READINESS_RELEASE_REF: '.($releaseRef !== '' ? $releaseRef : '-'));

        $this->recordAudit(
            status: 'pass',
            reportPath: $reportPath,
            issues: [],
            signoffPath: $resolvedOutputPath,
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    protected function schemaRules(): array
    {
        return [
            'profile' => ['required', 'string', 'in:production'],
            'strict_full' => ['required', 'boolean'],
            'dry_run' => ['required', 'boolean'],
            'with_finance' => ['required', 'boolean'],
            'run_tests' => ['required', 'boolean'],
            'test_filter' => ['nullable', 'string'],
            'started_at' => ['required', 'string'],
            'finished_at' => ['required', 'string'],
            'status' => ['required', 'string', 'in:dry_run,pass,fail'],
            'duration_ms' => ['required', 'integer', 'min:0'],
            'steps_plan' => ['required', 'array', 'min:1'],
            'steps_plan.*.name' => ['required', 'string'],
            'steps_plan.*.command' => ['required', 'string'],
            'steps_plan.*.timeout_seconds' => ['required', 'integer', 'min:1'],
            'steps_run' => ['required', 'array'],
            'steps_run.*.name' => ['required', 'string'],
            'steps_run.*.command' => ['required', 'string'],
            'steps_run.*.timeout_seconds' => ['required', 'integer', 'min:1'],
            'steps_run.*.duration_ms' => ['required', 'integer', 'min:0'],
            'steps_run.*.exit_code' => ['required', 'integer'],
            'steps_run.*.successful' => ['required', 'boolean'],
            'steps_run.*.output' => ['present', 'string'],
            'steps_run.*.error_output' => ['present', 'string'],
            'failures' => ['present', 'array'],
            'failures.*.name' => ['required', 'string'],
            'failures.*.exit_code' => ['required', 'integer'],
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    protected function collectBusinessIssues(array $report): array
    {
        $issues = [];
        $status = (string) ($report['status'] ?? '');
        $dryRun = (bool) ($report['dry_run'] ?? false);
        $failures = (array) ($report['failures'] ?? []);
        $stepsPlan = (array) ($report['steps_plan'] ?? []);
        $stepsRun = (array) ($report['steps_run'] ?? []);

        if ($status !== 'pass') {
            $issues[] = 'report_status_phai_pass';
        }

        if ($dryRun) {
            $issues[] = 'report_dry_run_khong_duoc_ky_deploy';
        }

        if ($failures !== []) {
            $issues[] = 'report_failures_phai_rong';
        }

        if (count($stepsRun) !== count($stepsPlan)) {
            $issues[] = 'steps_run_khong_khop_steps_plan';
        }

        foreach ($stepsRun as $index => $step) {
            $successful = (bool) Arr::get($step, 'successful', false);
            $exitCode = (int) Arr::get($step, 'exit_code', 1);
            if (! $successful || $exitCode !== 0) {
                $issues[] = 'step_'.($index + 1).'_khong_thanh_cong';
            }
        }

        return $issues;
    }

    protected function resolveReportPath(string $input): ?string
    {
        $candidate = trim($input);
        if ($candidate === '') {
            return null;
        }

        if ($this->isAbsolutePath($candidate)) {
            return $candidate;
        }

        $basePathCandidate = base_path($candidate);
        if (is_file($basePathCandidate)) {
            return $basePathCandidate;
        }

        $storageCandidate = storage_path('app/'.ltrim($candidate, '/'));
        if (is_file($storageCandidate)) {
            return $storageCandidate;
        }

        return $basePathCandidate;
    }

    protected function resolveOutputPath(string $output): string
    {
        $candidate = trim($output);
        if ($candidate === '') {
            return $this->defaultOutputPath(storage_path('app/release-readiness/unknown.json'));
        }

        if ($this->isAbsolutePath($candidate)) {
            return $candidate;
        }

        return base_path($candidate);
    }

    protected function defaultOutputPath(string $reportPath): string
    {
        $basename = pathinfo($reportPath, PATHINFO_FILENAME);

        return storage_path('app/release-readiness/signoff/'.$basename.'-signoff-'.now()->format('Ymd_His').'.json');
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param  array<int, string>  $issues
     */
    protected function recordAudit(string $status, string $reportPath, array $issues, ?string $signoffPath): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $status === 'pass' ? AuditLog::ACTION_APPROVE : AuditLog::ACTION_FAIL,
            actorId: auth()->id(),
            metadata: [
                'command' => 'ops:verify-production-readiness-report',
                'status' => $status,
                'report_path' => $reportPath,
                'issues' => $issues,
                'signoff_path' => $signoffPath,
                'strict' => (bool) $this->option('strict'),
            ],
        );
    }
}
