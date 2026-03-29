<?php

namespace App\Services;

use App\Models\Note;
use App\Models\PlanItem;
use App\Models\ReportCareQueueDailyAggregate;
use App\Models\ReportRevenueDailyAggregate;
use Illuminate\Database\Eloquent\Builder;

class HotReportAggregateReadModelService
{
    public function __construct(
        protected HotReportAggregateReadinessService $hotReportAggregateReadinessService,
    ) {}

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function shouldUseRevenueAggregate(array $scopeIds, ?string $from, ?string $until): bool
    {
        return $this->hotReportAggregateReadinessService->shouldUseRevenueAggregate($scopeIds, $from, $until);
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function shouldUseCareAggregate(array $scopeIds, ?string $from, ?string $until): bool
    {
        return $this->hotReportAggregateReadinessService->shouldUseCareAggregate($scopeIds, $from, $until);
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function revenueBreakdownQuery(array $scopeIds): Builder
    {
        if ($scopeIds === []) {
            return ReportRevenueDailyAggregate::query()
                ->from('report_revenue_daily_aggregates as revenue_daily')
                ->whereRaw('1 = 0');
        }

        return ReportRevenueDailyAggregate::query()
            ->from('report_revenue_daily_aggregates as revenue_daily')
            ->selectRaw('
                revenue_daily.service_id as service_id,
                MAX(revenue_daily.service_name) as service_name,
                MAX(revenue_daily.category_name) as category_name,
                SUM(revenue_daily.total_count) as total_count,
                SUM(revenue_daily.total_revenue) as total_revenue,
                MAX(revenue_daily.snapshot_date) as snapshot_date
            ')
            ->whereIn('revenue_daily.branch_scope_id', $scopeIds)
            ->groupBy('revenue_daily.service_id');
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function revenueCategoryBreakdownQuery(array $scopeIds): Builder
    {
        if ($scopeIds === []) {
            return ReportRevenueDailyAggregate::query()
                ->from('report_revenue_daily_aggregates as revenue_daily')
                ->whereRaw('1 = 0');
        }

        return ReportRevenueDailyAggregate::query()
            ->from('report_revenue_daily_aggregates as revenue_daily')
            ->selectRaw('
                MAX(revenue_daily.category_name) as category_name,
                SUM(revenue_daily.total_count) as total_count,
                SUM(revenue_daily.total_revenue) as total_revenue,
                MAX(revenue_daily.snapshot_date) as snapshot_date
            ')
            ->whereIn('revenue_daily.branch_scope_id', $scopeIds)
            ->groupBy('revenue_daily.category_name');
    }

    /**
     * @param  array<int, int>  $scopeIds
     * @return array{total_procedures:int,total_revenue:float}
     */
    public function revenueSummary(array $scopeIds, ?string $from, ?string $until): array
    {
        if ($scopeIds === []) {
            return [
                'total_procedures' => 0,
                'total_revenue' => 0.0,
            ];
        }

        $query = ReportRevenueDailyAggregate::query()
            ->whereIn('branch_scope_id', $scopeIds);

        $this->applyDateRange($query, 'snapshot_date', $from, $until);

        return [
            'total_procedures' => (int) (clone $query)->sum('total_count'),
            'total_revenue' => (float) (clone $query)->sum('total_revenue'),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function liveRevenueBreakdownQuery(?array $branchIds): Builder
    {
        $query = PlanItem::query()
            ->selectRaw('
                plan_items.service_id as service_id,
                COALESCE(services.name, CONCAT("Service #", plan_items.service_id)) as service_name,
                service_categories.name as category_name,
                COUNT(plan_items.id) as total_count,
                COALESCE(SUM(COALESCE(plan_items.final_amount, 0)), 0) as total_revenue
            ')
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->groupBy('plan_items.service_id', 'services.name', 'service_categories.name');

        return $this->applyPlanItemBranchScope($query, $branchIds);
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function liveRevenueCategoryBreakdownQuery(?array $branchIds): Builder
    {
        $query = PlanItem::query()
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->selectRaw('
                services.category_id as category_id,
                service_categories.name as category_name,
                COUNT(*) as total_count,
                COALESCE(SUM(COALESCE(plan_items.final_amount, 0)), 0) as total_revenue,
                MAX(plan_items.created_at) as created_at
            ')
            ->groupBy('services.category_id', 'service_categories.name');

        return $this->applyPlanItemBranchScope($query, $branchIds);
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_procedures:int,total_revenue:float}
     */
    public function liveRevenueSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        if ($branchIds === []) {
            return [
                'total_procedures' => 0,
                'total_revenue' => 0.0,
            ];
        }

        $query = $this->applyPlanItemBranchScope(PlanItem::query(), $branchIds);
        $this->applyDateRange($query, 'plan_items.created_at', $from, $until);

        return [
            'total_procedures' => (clone $query)->count(),
            'total_revenue' => (float) (clone $query)->sum('final_amount'),
        ];
    }

    /**
     * @param  array<int, int>  $scopeIds
     */
    public function careBreakdownQuery(array $scopeIds): Builder
    {
        if ($scopeIds === []) {
            return ReportCareQueueDailyAggregate::query()
                ->from('report_care_queue_daily_aggregates as care_daily')
                ->whereRaw('1 = 0');
        }

        return ReportCareQueueDailyAggregate::query()
            ->from('report_care_queue_daily_aggregates as care_daily')
            ->selectRaw('
                care_daily.care_type as care_type,
                MAX(care_daily.care_type_label) as care_type_label,
                care_daily.care_status as care_status,
                MAX(care_daily.care_status_label) as care_status_label,
                SUM(care_daily.total_count) as total_count,
                MAX(care_daily.latest_care_at) as care_at,
                MAX(care_daily.snapshot_date) as snapshot_date
            ')
            ->whereIn('care_daily.branch_scope_id', $scopeIds)
            ->groupBy('care_daily.care_type', 'care_daily.care_status');
    }

    /**
     * @param  array<int, int>  $scopeIds
     * @return array{total:int,completed:int,planned:int}
     */
    public function careSummary(array $scopeIds, ?string $from, ?string $until): array
    {
        if ($scopeIds === []) {
            return [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
            ];
        }

        $query = ReportCareQueueDailyAggregate::query()
            ->whereIn('branch_scope_id', $scopeIds);

        $this->applyDateRange($query, 'snapshot_date', $from, $until);

        return [
            'total' => (int) (clone $query)->sum('total_count'),
            'completed' => (int) (clone $query)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
                ->sum('total_count'),
            'planned' => (int) (clone $query)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))
                ->sum('total_count'),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function liveCareBreakdownQuery(?array $branchIds): Builder
    {
        $query = Note::query()
            ->selectRaw('care_type, care_status, count(*) as total_count, max(care_at) as care_at')
            ->whereNotNull('care_type')
            ->whereNotNull('care_status')
            ->groupBy('care_type', 'care_status');

        return $this->applyCareBranchScope($query, $branchIds);
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total:int,completed:int,planned:int}
     */
    public function liveCareSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        if ($branchIds === []) {
            return [
                'total' => 0,
                'completed' => 0,
                'planned' => 0,
            ];
        }

        $query = Note::query()
            ->whereNotNull('care_type')
            ->whereNotNull('care_status');

        $query = $this->applyCareBranchScope($query, $branchIds);
        $this->applyDateRange($query, 'care_at', $from, $until);

        return [
            'total' => (clone $query)->count(),
            'completed' => (int) (clone $query)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
                ->count(),
            'planned' => (int) (clone $query)
                ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]))
                ->count(),
        ];
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $until): Builder
    {
        if (filled($from)) {
            $query->whereDate($column, '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyPlanItemBranchScope(Builder $query, ?array $branchIds): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('treatmentPlan', fn (Builder $relationQuery) => $relationQuery->whereIn('branch_id', $branchIds));
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyCareBranchScope(Builder $query, ?array $branchIds): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scopeQuery) use ($branchIds): void {
            $scopeQuery->whereIn('branch_id', $branchIds)
                ->orWhere(function (Builder $legacyQuery) use ($branchIds): void {
                    $legacyQuery->whereNull('branch_id')
                        ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->whereIn('first_branch_id', $branchIds));
                });
        });
    }
}
