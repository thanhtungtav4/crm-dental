<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AutomationActorResolver;
use App\Support\ActionPermission;
use Illuminate\Console\Command;

class CheckAutomationActorHealth extends Command
{
    protected $signature = 'security:check-automation-actor {--strict : Fail nếu có cả warning về least-privilege role}';

    protected $description = 'Kiểm tra scheduler automation actor (service account, role, permission) và ghi audit log cảnh báo.';

    public function __construct(protected AutomationActorResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $report = $this->resolver->healthReport(
            permission: ActionPermission::AUTOMATION_RUN,
            enforceRequiredRole: true,
        );

        $issues = collect($report['issues'] ?? []);
        $errors = $issues->where('severity', 'error')->values();
        $warnings = $issues->where('severity', 'warning')->values();
        $actorId = data_get($report, 'actor_id');
        $roles = (array) data_get($report, 'roles', []);
        $requiredRole = (string) data_get($report, 'required_role', '');

        $this->line('AUTOMATION_ACTOR_ID: '.($actorId !== null ? (string) $actorId : 'null'));
        $this->line('AUTOMATION_REQUIRED_ROLE: '.($requiredRole !== '' ? $requiredRole : '(none)'));
        $this->line('AUTOMATION_PERMISSION: '.ActionPermission::AUTOMATION_RUN);
        $this->line('AUTOMATION_ACTOR_ROLES: '.($roles !== [] ? implode(', ', $roles) : '(none)'));

        if ($issues->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Severity', 'Code', 'Message'],
                $issues
                    ->map(fn (array $issue): array => [
                        strtoupper((string) ($issue['severity'] ?? 'unknown')),
                        (string) ($issue['code'] ?? 'unknown'),
                        (string) ($issue['message'] ?? ''),
                    ])
                    ->all(),
            );
        }

        $shouldFail = $errors->isNotEmpty() || ($strict && $warnings->isNotEmpty());
        $action = $shouldFail ? AuditLog::ACTION_FAIL : AuditLog::ACTION_RUN;

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: $action,
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            metadata: [
                'channel' => 'automation_actor_health',
                'strict' => $strict,
                'required_role' => $requiredRole,
                'permission' => ActionPermission::AUTOMATION_RUN,
                'roles' => $roles,
                'error_count' => $errors->count(),
                'warning_count' => $warnings->count(),
                'issues' => $issues->all(),
            ],
        );

        if ($shouldFail) {
            $this->error('Automation actor health: FAIL.');

            return self::FAILURE;
        }

        $this->info('Automation actor health: OK.');

        return self::SUCCESS;
    }
}
