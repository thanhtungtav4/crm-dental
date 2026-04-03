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

    /**
     * @return Collection<int, array{
     *     changed_at_label:string,
     *     changed_by_name:string,
     *     setting_label:string,
     *     setting_key:string,
     *     change_reason:?string,
     *     grace_expires_at_label:?string,
     *     old_value:?string,
     *     new_value:?string
     * }>
     */
    public function renderedRecentLogs(int $limit = 20): Collection
    {
        return $this->recentLogs($limit)
            ->map(function (ClinicSettingLog $log): array {
                return [
                    'changed_at_label' => optional($log->changed_at)->format('d/m/Y H:i:s') ?? '-',
                    'changed_by_name' => $log->changedBy?->name ?? 'Hệ thống',
                    'setting_label' => (string) ($log->setting_label ?: $log->setting_key),
                    'setting_key' => (string) $log->setting_key,
                    'change_reason' => filled($log->change_reason ?? null)
                        ? (string) $log->change_reason
                        : null,
                    'grace_expires_at_label' => filled(data_get($log->context, 'grace_expires_at'))
                        ? \Illuminate\Support\Carbon::parse((string) data_get($log->context, 'grace_expires_at'))->format('d/m/Y H:i')
                        : null,
                    'old_value' => $log->old_value !== null ? (string) $log->old_value : null,
                    'new_value' => $log->new_value !== null ? (string) $log->new_value : null,
                ];
            })
            ->values();
    }
}
