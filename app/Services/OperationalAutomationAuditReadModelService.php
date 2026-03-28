<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalAutomationAuditReadModelService
{
    /**
     * @return array<int, string>
     */
    public function trackedCommands(): array
    {
        return [
            'security:check-automation-actor',
            'ops:create-backup-artifact',
            'ops:check-backup-health',
            'ops:run-restore-drill',
            'ops:run-release-gates',
            'ops:run-production-readiness',
            'ops:verify-production-readiness-report',
            'ops:check-alert-runbook-map',
            'ops:check-observability-health',
            'reports:explain-ops-hotpaths',
            'integrations:revoke-rotated-secrets',
            'integrations:prune-operational-data',
            'reports:snapshot-operational-kpis',
            'reports:check-snapshot-sla',
            'reports:compare-snapshots',
            'reports:snapshot-hot-aggregates',
            'zns:sync-automation-events',
            'zns:prune-operational-data',
            'zns:run-campaigns',
            'popups:dispatch-due',
            'popups:prune',
            'photos:prune',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function trackedChannels(): array
    {
        return [
            'automation_actor_health',
            'backup_artifact',
            'release_gates',
            'production_readiness',
            'observability_health',
        ];
    }

    public function baseTrackedAutomationQuery(): Builder
    {
        return AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_AUTOMATION)
            ->where(function (Builder $query): void {
                foreach ($this->trackedCommands() as $command) {
                    $query->orWhere(function (Builder $nested) use ($command): void {
                        $nested
                            ->where('metadata->command', $command)
                            ->orWhere('metadata->target_command', $command);
                    });
                }

                foreach ($this->trackedChannels() as $channel) {
                    $query->orWhere('metadata->channel', $channel);
                }
            });
    }

    public function recentFailureCount(CarbonInterface $windowStartedAt): int
    {
        return $this->baseTrackedAutomationQuery()
            ->where('action', AuditLog::ACTION_FAIL)
            ->where('created_at', '>=', $windowStartedAt)
            ->count();
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentRuns(int $limit = 12): Collection
    {
        return $this->baseTrackedAutomationQuery()
            ->with('actor')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
