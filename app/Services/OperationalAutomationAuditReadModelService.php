<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\OpsAutomationCatalog;
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
        return OpsAutomationCatalog::trackedCommands();
    }

    /**
     * @return array<int, string>
     */
    public function trackedChannels(): array
    {
        return OpsAutomationCatalog::trackedChannels();
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
