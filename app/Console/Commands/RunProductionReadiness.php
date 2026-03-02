<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class RunProductionReadiness extends Command
{
    protected $signature = 'ops:run-production-readiness
        {--with-finance : Chay gate doi soat finance attribution trong release gates}
        {--from= : Tu ngay (Y-m-d) cho finance reconciliation}
        {--to= : Den ngay (Y-m-d) cho finance reconciliation}
        {--run-tests : Chay php artisan test sau khi qua release gates}
        {--test-filter= : Filter test (duoc truyen vao --filter)}
        {--strict-full : Bat buoc chay full test suite (bo qua --test-filter)}
        {--report= : Duong dan JSON artifact report readiness}
        {--dry-run : Chi in checklist, khong thuc thi}
        {--fail-fast : Dung ngay khi co buoc that bai}';

    protected $description = 'Chay go-live readiness pack mot lenh (release gates production + optional test suite).';

    public function handle(): int
    {
        $strictFull = (bool) $this->option('strict-full');
        if ($strictFull && filled($this->option('test-filter'))) {
            $this->warn('STRICT_FULL_MODE: --test-filter se bi bo qua, command se chay full suite.');
        }

        $steps = $this->buildSteps();
        $reportPath = (string) ($this->option('report') ?: $this->defaultReportPath());
        $startedAt = now();
        $startedAtMicro = microtime(true);
        $report = [
            'profile' => 'production',
            'strict_full' => $strictFull,
            'dry_run' => (bool) $this->option('dry-run'),
            'with_finance' => (bool) $this->option('with-finance'),
            'run_tests' => (bool) $this->option('run-tests') || $strictFull,
            'test_filter' => $strictFull ? null : (string) ($this->option('test-filter') ?? ''),
            'started_at' => $startedAt->toDateTimeString(),
            'status' => 'running',
            'steps_plan' => collect($steps)->map(fn (array $step): array => [
                'name' => (string) Arr::get($step, 'name', ''),
                'command' => (string) Arr::get($step, 'display_command', ''),
                'timeout_seconds' => (int) Arr::get($step, 'timeout', 0),
            ])->values()->all(),
            'steps_run' => [],
            'failures' => [],
        ];

        $this->line('PRODUCTION_READINESS_PROFILE: production');
        $this->table(
            ['#', 'Step', 'Command', 'Timeout(s)'],
            collect($steps)->values()->map(function (array $step, int $index): array {
                return [
                    $index + 1,
                    Arr::get($step, 'name', '-'),
                    Arr::get($step, 'display_command', '-'),
                    (string) Arr::get($step, 'timeout', '-'),
                ];
            })->all(),
        );

        if ((bool) $this->option('dry-run')) {
            $this->info('PRODUCTION_READINESS_STATUS: DRY_RUN');
            $report['status'] = 'dry_run';
            $report['finished_at'] = now()->toDateTimeString();
            $report['duration_ms'] = (int) round(max(0, (microtime(true) - $startedAtMicro) * 1000));
            $this->writeReport($reportPath, $report);
            $this->line('PRODUCTION_READINESS_REPORT: '.$reportPath);

            return self::SUCCESS;
        }

        $failures = [];
        $failFast = (bool) $this->option('fail-fast');
        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $name = (string) Arr::get($step, 'name', 'Step');
            $command = (array) Arr::get($step, 'command', []);
            $timeout = (int) Arr::get($step, 'timeout', 600);
            $environment = (array) Arr::get($step, 'env', []);

            $this->newLine();
            $this->line(sprintf('[%d/%d] %s', $index + 1, $totalSteps, $name));

            $stepStartedAt = microtime(true);
            $process = Process::path(base_path())
                ->timeout($timeout);

            if ($environment !== []) {
                $process = $process->env($environment);
            }

            $result = $process->run($command);
            $durationMs = (int) round((microtime(true) - $stepStartedAt) * 1000);

            $output = trim((string) $result->output());
            $errorOutput = trim((string) $result->errorOutput());

            if ($output !== '') {
                $this->line('OUTPUT: '.Str::limit($output, 2000));
            }

            if ($errorOutput !== '') {
                $this->line('ERROR: '.Str::limit($errorOutput, 2000));
            }

            $report['steps_run'][] = [
                'name' => $name,
                'command' => (string) Arr::get($step, 'display_command', ''),
                'timeout_seconds' => $timeout,
                'duration_ms' => $durationMs,
                'exit_code' => $result->exitCode(),
                'successful' => $result->successful(),
                'output' => Str::limit($output, 4000),
                'error_output' => Str::limit($errorOutput, 4000),
            ];

            if (! $result->successful()) {
                $failures[] = [
                    'name' => $name,
                    'exit_code' => $result->exitCode(),
                ];

                if ($failFast) {
                    break;
                }
            }
        }

        if ($failures !== []) {
            $this->error('PRODUCTION_READINESS_STATUS: FAIL');
            $report['status'] = 'fail';
            $report['failures'] = $failures;
            $report['finished_at'] = now()->toDateTimeString();
            $report['duration_ms'] = (int) round(max(0, (microtime(true) - $startedAtMicro) * 1000));

            foreach ($failures as $failure) {
                $this->line(sprintf(
                    '- %s (exit=%d)',
                    $failure['name'],
                    (int) $failure['exit_code'],
                ));
            }
            $this->writeReport($reportPath, $report);
            $this->line('PRODUCTION_READINESS_REPORT: '.$reportPath);

            return self::FAILURE;
        }

        $this->info('PRODUCTION_READINESS_STATUS: PASS');
        $report['status'] = 'pass';
        $report['finished_at'] = now()->toDateTimeString();
        $report['duration_ms'] = (int) round(max(0, (microtime(true) - $startedAtMicro) * 1000));
        $this->writeReport($reportPath, $report);
        $this->line('PRODUCTION_READINESS_REPORT: '.$reportPath);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name:string, command:array<int, string>, display_command:string, timeout:int, env?:array<string, string|bool>}>
     */
    protected function buildSteps(): array
    {
        $strictFull = (bool) $this->option('strict-full');
        $shouldRunTests = (bool) $this->option('run-tests') || $strictFull;
        $releaseCommand = [
            PHP_BINARY,
            base_path('artisan'),
            'ops:run-release-gates',
            '--profile=production',
        ];

        if ((bool) $this->option('with-finance')) {
            $releaseCommand[] = '--with-finance';

            if (filled($this->option('from'))) {
                $releaseCommand[] = '--from='.(string) $this->option('from');
            }

            if (filled($this->option('to'))) {
                $releaseCommand[] = '--to='.(string) $this->option('to');
            }
        }

        $steps = [[
            'name' => 'Release gates (production)',
            'command' => $releaseCommand,
            'display_command' => implode(' ', $releaseCommand),
            'timeout' => 1800,
        ]];

        if ($shouldRunTests) {
            $testCommand = [
                PHP_BINARY,
                base_path('artisan'),
                'test',
            ];

            if (! $strictFull && filled($this->option('test-filter'))) {
                $testCommand[] = '--filter='.(string) $this->option('test-filter');
            }

            $steps[] = [
                'name' => $strictFull ? 'Application test suite (full)' : 'Application test suite',
                'command' => $testCommand,
                'display_command' => implode(' ', $testCommand),
                'timeout' => 7200,
                'env' => $this->testingProcessEnvironment(),
            ];
        }

        return $steps;
    }

    /**
     * @return array<string, string|bool>
     */
    protected function testingProcessEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];
    }

    protected function defaultReportPath(): string
    {
        return storage_path('app/release-readiness/production-readiness-'.now()->format('Ymd_His').'.json');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function writeReport(string $path, array $report): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
