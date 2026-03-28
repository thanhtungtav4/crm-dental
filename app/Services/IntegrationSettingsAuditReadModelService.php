<?php

namespace App\Services;

use App\Models\ClinicSettingLog;
use Illuminate\Support\Collection;

class IntegrationSettingsAuditReadModelService
{
    /**
     * @return Collection<int, ClinicSettingLog>
     */
    public function recentLogs(int $limit = 20): Collection
    {
        return ClinicSettingLog::query()
            ->with('changedBy:id,name')
            ->latest('changed_at')
            ->limit(max(1, $limit))
            ->get();
    }
}
