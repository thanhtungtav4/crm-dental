<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GovernanceAuditReadModelService
{
    /**
     * @return array<int, string>
     */
    public function trackedEntityTypes(): array
    {
        return [
            AuditLog::ENTITY_SECURITY,
            AuditLog::ENTITY_AUTOMATION,
            AuditLog::ENTITY_BRANCH_TRANSFER,
            AuditLog::ENTITY_INVOICE,
        ];
    }

    public function baseQuery(User $user): Builder
    {
        return AuditLog::query()
            ->with('actor')
            ->visibleTo($user)
            ->whereIn('entity_type', $this->trackedEntityTypes());
    }

    /**
     * @return Collection<int, AuditLog>
     */
    public function recentAudits(User $user, int $limit = 5): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        return $this->baseQuery($user)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
