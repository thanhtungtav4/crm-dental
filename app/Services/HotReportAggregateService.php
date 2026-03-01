<?php

namespace App\Services;

use App\Models\Note;
use App\Models\PlanItem;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HotReportAggregateService
{
    /**
     * @return array{revenue_rows:int,care_rows:int}
     */
    public function snapshotForDate(Carbon $snapshotDate, ?int $branchId = null): array
    {
        $snapshotDateString = $snapshotDate->toDateString();
        $branchScopeId = $branchId ?? 0;
        $generatedAt = now();

        $revenueRows = $this->buildRevenueRows($snapshotDate, $branchId, $generatedAt);
        $careRows = $this->buildCareRows($snapshotDate, $branchId, $generatedAt);

        DB::transaction(function () use ($snapshotDateString, $branchScopeId, $revenueRows, $careRows): void {
            ReportRevenueDailyAggregate::query()
                ->whereDate('snapshot_date', $snapshotDateString)
                ->where('branch_scope_id', $branchScopeId)
                ->delete();

            if ($revenueRows !== []) {
                ReportRevenueDailyAggregate::query()->upsert(
                    values: $revenueRows,
                    uniqueBy: ['snapshot_date', 'branch_scope_id', 'service_id'],
                    update: ['branch_id', 'service_name', 'category_name', 'total_count', 'total_revenue', 'generated_at', 'updated_at'],
                );
            }

            ReportCareQueueDailyAggregate::query()
                ->whereDate('snapshot_date', $snapshotDateString)
                ->where('branch_scope_id', $branchScopeId)
                ->delete();

            if ($careRows !== []) {
                ReportCareQueueDailyAggregate::query()->upsert(
                    values: $careRows,
                    uniqueBy: ['snapshot_date', 'branch_scope_id', 'care_type', 'care_status'],
                    update: ['branch_id', 'care_type_label', 'care_status_label', 'total_count', 'latest_care_at', 'generated_at', 'updated_at'],
                );
            }
        }, 3);

        return [
            'revenue_rows' => count($revenueRows),
            'care_rows' => count($careRows),
        ];
    }

    /**
     * @return array<int, array{
     *     snapshot_date:string,
     *     branch_id:int|null,
     *     branch_scope_id:int,
     *     service_id:int,
     *     service_name:string,
     *     category_name:string|null,
     *     total_count:int,
     *     total_revenue:float,
     *     generated_at:\Carbon\CarbonInterface,
     *     created_at:\Carbon\CarbonInterface,
     *     updated_at:\Carbon\CarbonInterface
     * }>
     */
    protected function buildRevenueRows(Carbon $snapshotDate, ?int $branchId, Carbon $generatedAt): array
    {
        $snapshotDateString = $snapshotDate->toDateString();
        $scopeId = $branchId ?? 0;

        $rows = PlanItem::query()
            ->selectRaw('
                plan_items.service_id as service_id,
                COALESCE(services.name, CONCAT("Service #", plan_items.service_id)) as service_name,
                service_categories.name as category_name,
                COUNT(plan_items.id) as total_count,
                COALESCE(SUM(COALESCE(plan_items.final_amount, 0)), 0) as total_revenue
            ')
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->whereDate('plan_items.created_at', $snapshotDateString)
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereHas(
                    'treatmentPlan',
                    fn (Builder $innerQuery) => $innerQuery->where('branch_id', $branchId)
                )
            )
            ->groupBy('plan_items.service_id', 'services.name', 'service_categories.name')
            ->get();

        return $rows->map(function (object $row) use ($snapshotDateString, $branchId, $scopeId, $generatedAt): array {
            return [
                'snapshot_date' => $snapshotDateString,
                'branch_id' => $branchId,
                'branch_scope_id' => $scopeId,
                'service_id' => (int) $row->service_id,
                'service_name' => (string) $row->service_name,
                'category_name' => $row->category_name ? (string) $row->category_name : null,
                'total_count' => (int) $row->total_count,
                'total_revenue' => round((float) $row->total_revenue, 2),
                'generated_at' => $generatedAt,
                'created_at' => $generatedAt,
                'updated_at' => $generatedAt,
            ];
        })->all();
    }

    /**
     * @return array<int, array{
     *     snapshot_date:string,
     *     branch_id:int|null,
     *     branch_scope_id:int,
     *     care_type:string,
     *     care_type_label:string|null,
     *     care_status:string,
     *     care_status_label:string|null,
     *     total_count:int,
     *     latest_care_at:string|null,
     *     generated_at:\Carbon\CarbonInterface,
     *     created_at:\Carbon\CarbonInterface,
     *     updated_at:\Carbon\CarbonInterface
     * }>
     */
    protected function buildCareRows(Carbon $snapshotDate, ?int $branchId, Carbon $generatedAt): array
    {
        $snapshotDateString = $snapshotDate->toDateString();
        $scopeId = $branchId ?? 0;
        $careTypeLabels = ClinicRuntimeSettings::careTypeDisplayOptions();

        $rows = Note::query()
            ->selectRaw('care_type, care_status, COUNT(id) as total_count, MAX(care_at) as latest_care_at')
            ->whereNotNull('care_type')
            ->whereNotNull('care_status')
            ->whereDate('care_at', $snapshotDateString)
            ->when($branchId !== null, function (Builder $query) use ($branchId): void {
                $query->where(function (Builder $scopeQuery) use ($branchId): void {
                    $scopeQuery->where('branch_id', $branchId)
                        ->orWhere(function (Builder $legacyQuery) use ($branchId): void {
                            $legacyQuery->whereNull('branch_id')
                                ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->where('first_branch_id', $branchId));
                        });
                });
            })
            ->groupBy('care_type', 'care_status')
            ->get();

        return $rows->map(function (object $row) use ($snapshotDateString, $branchId, $scopeId, $careTypeLabels, $generatedAt): array {
            $careType = (string) $row->care_type;
            $careStatus = (string) $row->care_status;

            return [
                'snapshot_date' => $snapshotDateString,
                'branch_id' => $branchId,
                'branch_scope_id' => $scopeId,
                'care_type' => $careType,
                'care_type_label' => $careTypeLabels[$careType] ?? 'Chăm sóc chung',
                'care_status' => $careStatus,
                'care_status_label' => Note::careStatusLabel($careStatus),
                'total_count' => (int) $row->total_count,
                'latest_care_at' => $row->latest_care_at ? (string) $row->latest_care_at : null,
                'generated_at' => $generatedAt,
                'created_at' => $generatedAt,
                'updated_at' => $generatedAt,
            ];
        })->all();
    }
}
