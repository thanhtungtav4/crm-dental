<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
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
        {--dry-run : Chi in checklist, khong thuc thi}
        {--fail-fast : Dung ngay khi co buoc that bai}';

    protected $description = 'Chay go-live readiness pack mot lenh (release gates production + optional test suite).';

    public function handle(): int
    {
        $steps = $this->buildSteps();

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

            return self::SUCCESS;
        }

        $failures = [];
        $failFast = (bool) $this->option('fail-fast');
        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $name = (string) Arr::get($step, 'name', 'Step');
            $command = (array) Arr::get($step, 'command', []);
            $timeout = (int) Arr::get($step, 'timeout', 600);

            $this->newLine();
            $this->line(sprintf('[%d/%d] %s', $index + 1, $totalSteps, $name));

            $result = Process::path(base_path())
                ->timeout($timeout)
                ->run($command);

            $output = trim((string) $result->output());
            $errorOutput = trim((string) $result->errorOutput());

            if ($output !== '') {
                $this->line('OUTPUT: '.Str::limit($output, 2000));
            }

            if ($errorOutput !== '') {
                $this->line('ERROR: '.Str::limit($errorOutput, 2000));
            }

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

            foreach ($failures as $failure) {
                $this->line(sprintf(
                    '- %s (exit=%d)',
                    $failure['name'],
                    (int) $failure['exit_code'],
                ));
            }

            return self::FAILURE;
        }

        $this->info('PRODUCTION_READINESS_STATUS: PASS');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name:string, command:array<int, string>, display_command:string, timeout:int}>
     */
    protected function buildSteps(): array
    {
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

        if ((bool) $this->option('run-tests')) {
            $testCommand = [
                PHP_BINARY,
                base_path('artisan'),
                'test',
            ];

            if (filled($this->option('test-filter'))) {
                $testCommand[] = '--filter='.(string) $this->option('test-filter');
            }

            $steps[] = [
                'name' => 'Application test suite',
                'command' => $testCommand,
                'display_command' => implode(' ', $testCommand),
                'timeout' => 7200,
            ];
        }

        return $steps;
    }
}
