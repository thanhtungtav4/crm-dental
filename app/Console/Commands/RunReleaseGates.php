<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\OpsCommandAuthorizer;
use App\Support\OpsReleaseGateCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class RunReleaseGates extends Command
{
    public const PROFILE_CI = 'ci';

    public const PROFILE_OPS = 'ops';

    public const PROFILE_PRODUCTION = 'production';

    protected $signature = 'ops:run-release-gates
        {--profile=ci : ci|ops|production}
        {--with-finance : Bổ sung gate đối soát branch attribution tài chính}
        {--from= : Từ ngày (Y-m-d) cho finance reconciliation}
        {--to= : Đến ngày (Y-m-d) cho finance reconciliation}
        {--dry-run : Chỉ in danh sách gate, không thực thi}';

    protected $description = 'Chạy checklist release gate cho CRM multi-branch trước deploy.';

    public function __construct(protected OpsCommandAuthorizer $authorizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $actorId = $this->authorizer->authorize(
            'Bạn không có quyền chạy release gates.',
        );

        $profile = $this->normalizeProfile((string) $this->option('profile'));

        if ($profile === null) {
            $this->error('Profile không hợp lệ. Chỉ chấp nhận: ci, ops, production.');

            return self::FAILURE;
        }

        $steps = $this->buildSteps(
            profile: $profile,
            withFinance: (bool) $this->option('with-finance'),
        );

        if ($steps === []) {
            $this->error('Không có gate nào để chạy.');

            return self::FAILURE;
        }

        $this->line('RELEASE_GATE_PROFILE: '.$profile);
        $this->line('RELEASE_GATE_MODE: verify_only');
        $this->table(
            ['#', 'Gate', 'Command', 'Args'],
            collect($steps)->values()->map(function (array $step, int $index): array {
                return [
                    $index + 1,
                    Arr::get($step, 'name', '-'),
                    Arr::get($step, 'command', '-'),
                    $this->formatStepArguments((array) Arr::get($step, 'arguments', [])),
                ];
            })->all(),
        );

        if ((bool) $this->option('dry-run')) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: $actorId,
                metadata: [
                    'command' => 'ops:run-release-gates',
                    'profile' => $profile,
                    'with_finance' => (bool) $this->option('with-finance'),
                    'dry_run' => true,
                    'steps' => $steps,
                ],
            );

            $this->info('Dry-run completed. Không thực thi command nào.');

            return self::SUCCESS;
        }

        $failedSteps = [];
        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $name = (string) Arr::get($step, 'name', 'Gate');
            $command = (string) Arr::get($step, 'command');
            $arguments = (array) Arr::get($step, 'arguments', []);
            $stepLabel = '['.($index + 1).'/'.$totalSteps.'] '.$name;

            $this->newLine();
            $this->line($stepLabel.' -> '.$command);

            $exitCode = $this->call($command, $arguments);

            if ($exitCode !== self::SUCCESS) {
                $failedSteps[] = [
                    'name' => $name,
                    'command' => $command,
                    'exit_code' => $exitCode,
                ];
            }
        }

        if ($failedSteps !== []) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_FAIL,
                actorId: $actorId,
                metadata: [
                    'command' => 'ops:run-release-gates',
                    'profile' => $profile,
                    'with_finance' => (bool) $this->option('with-finance'),
                    'failed_steps' => $failedSteps,
                ],
            );

            $this->newLine();
            $this->error('Release gate: FAIL.');

            foreach ($failedSteps as $failedStep) {
                $this->line(sprintf(
                    '- %s (%s) exit=%d',
                    $failedStep['name'],
                    $failedStep['command'],
                    $failedStep['exit_code'],
                ));
            }

            return self::FAILURE;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: $actorId,
            metadata: [
                'command' => 'ops:run-release-gates',
                'profile' => $profile,
                'with_finance' => (bool) $this->option('with-finance'),
                'failed_steps' => [],
            ],
        );

        $this->newLine();
        $this->info('Release gate: PASS. Tất cả gate đã đạt.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name:string, command:string, arguments:array<string, mixed>}>
     */
    protected function buildSteps(string $profile, bool $withFinance): array
    {
        return OpsReleaseGateCatalog::steps(
            profile: $profile,
            withFinance: $withFinance,
            from: filled($this->option('from')) ? (string) $this->option('from') : null,
            to: filled($this->option('to')) ? (string) $this->option('to') : null,
        );
    }

    protected function normalizeProfile(string $profile): ?string
    {
        $normalized = strtolower(trim($profile));

        return in_array($normalized, [self::PROFILE_CI, self::PROFILE_OPS, self::PROFILE_PRODUCTION], true)
            ? $normalized
            : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function formatStepArguments(array $arguments): string
    {
        if ($arguments === []) {
            return '-';
        }

        return collect($arguments)
            ->map(function (mixed $value, string $key): string {
                if (is_bool($value)) {
                    return $value ? $key : $key.'=false';
                }

                if ($value === null || $value === '') {
                    return $key.'=';
                }

                return $key.'='.$value;
            })
            ->implode(', ');
    }
}
