<?php

namespace App\Console\Commands;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\SensitiveActionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class ReviewSensitiveActionCoverage extends Command
{
    protected $signature = 'security:review-sensitive-actions {--strict : Trả về exit code fail nếu thiếu guard/test coverage}';

    protected $description = 'Review checklist action nhạy cảm: guard points + authorization test coverage.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy security review checklist.',
        );

        $strict = (bool) $this->option('strict');
        $definitions = SensitiveActionRegistry::definitions();
        $permissions = ActionPermission::all();

        $missingInRegistry = array_values(array_diff($permissions, array_keys($definitions)));
        $extraInRegistry = array_values(array_diff(array_keys($definitions), $permissions));

        if ($missingInRegistry !== []) {
            $this->error('Thiếu định nghĩa trong SensitiveActionRegistry: '.implode(', ', $missingInRegistry));
        }

        if ($extraInRegistry !== []) {
            $this->warn('Registry có permission dư không còn trong ActionPermission::all(): '.implode(', ', $extraInRegistry));
        }

        $rows = [];
        $failedPermissions = [];

        foreach ($definitions as $permission => $definition) {
            $guardChecks = $this->evaluateMarkers((array) Arr::get($definition, 'guard_markers', []));
            $testChecks = $this->evaluateMarkers((array) Arr::get($definition, 'authorization_test_markers', []));

            $guardOk = $guardChecks !== [] && collect($guardChecks)->every(fn (array $check): bool => $check['ok']);
            $testOk = $testChecks !== [] && collect($testChecks)->every(fn (array $check): bool => $check['ok']);
            $isOk = $guardOk && $testOk && in_array($permission, $permissions, true);

            if (! $isOk) {
                $failedPermissions[] = $permission;
            }

            $rows[] = [
                $permission,
                implode(',', (array) Arr::get($definition, 'allowed_roles', [])),
                $guardOk ? 'ok' : 'missing',
                $testOk ? 'ok' : 'missing',
                $isOk ? 'pass' : 'fail',
            ];
        }

        $this->table(['Permission', 'Allowed roles', 'Guard markers', 'Test markers', 'Status'], $rows);

        if ($failedPermissions !== []) {
            $this->warn('Permissions fail checklist: '.implode(', ', $failedPermissions));
        }

        if (($missingInRegistry !== [] || $failedPermissions !== []) && $strict) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{path:string,contains:string}>  $markers
     * @return array<int, array{path:string,contains:string,ok:bool}>
     */
    protected function evaluateMarkers(array $markers): array
    {
        return collect($markers)
            ->map(function (array $marker): array {
                $path = (string) ($marker['path'] ?? '');
                $contains = (string) ($marker['contains'] ?? '');
                $absolutePath = base_path($path);
                $ok = false;

                if ($path !== '' && $contains !== '' && File::exists($absolutePath)) {
                    $contents = File::get($absolutePath);
                    $ok = str_contains($contents, $contains);
                }

                return [
                    'path' => $path,
                    'contains' => $contains,
                    'ok' => $ok,
                ];
            })
            ->values()
            ->all();
    }
}
