<?php

namespace App\Filament\Pages\Reports;

use App\Models\PlanItem;
use App\Models\ReportRevenueDailyAggregate;
use App\Services\HotReportAggregateReadinessService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class RevenueStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Doanh thu phòng khám';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'revenue-statistical';

    /**
     * @var array{key:string,value:bool}|null
     */
    protected ?array $usesRevenueAggregateDecision = null;

    protected function getDateColumn(): ?string
    {
        return $this->usesRevenueAggregate()
            ? 'snapshot_date'
            : 'plan_items.created_at';
    }

    protected function applyTableDateRangeFilter(Builder $query, array $data): Builder
    {
        return $this->applyDateRangeFilter(
            $query,
            $data,
            $this->usesRevenueAggregate() ? 'snapshot_date' : 'plan_items.created_at',
        );
    }

    protected function getTableQuery(): Builder
    {
        if ($this->usesRevenueAggregate()) {
            $scopeIds = $this->resolvedRevenueScopeIds();

            if ($scopeIds === []) {
                return ReportRevenueDailyAggregate::query()
                    ->from('report_revenue_daily_aggregates as revenue_daily')
                    ->whereRaw('1 = 0');
            }

            $query = ReportRevenueDailyAggregate::query()
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

            return $query;
        }

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

        return $this->applyRelatedBranchScope($query, 'treatmentPlan');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('category_name')
                ->label('Nhóm thủ thuật')
                ->default('-')
                ->wrap(),
            TextColumn::make('service_name')
                ->label('Tên thủ thuật')
                ->default('-')
                ->wrap(),
            TextColumn::make('total_count')
                ->label('Số lượng thủ thuật')
                ->numeric()
                ->sortable(),
            TextColumn::make('total_revenue')
                ->label('Doanh thu')
                ->money('VND', true)
                ->sortable(),
        ];
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions()),
        ]);
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Nhóm thủ thuật', 'value' => fn ($record) => $record->category_name],
            ['label' => 'Tên thủ thuật', 'value' => fn ($record) => $record->service_name],
            ['label' => 'Số lượng thủ thuật', 'value' => fn ($record) => $record->total_count],
            ['label' => 'Doanh thu', 'value' => fn ($record) => $record->total_revenue],
        ];
    }

    public function getStats(): array
    {
        if ($this->usesRevenueAggregate()) {
            $baseQuery = ReportRevenueDailyAggregate::query();
            $this->applyDateRange($baseQuery, 'snapshot_date');
            $scopeIds = $this->resolvedRevenueScopeIds();

            if ($scopeIds === []) {
                return [
                    ['label' => 'Tổng số lượng thủ thuật', 'value' => number_format(0)],
                    ['label' => 'Tổng thực thu', 'value' => number_format(0).' đ'],
                ];
            }

            $baseQuery->whereIn('branch_scope_id', $scopeIds);

            $totalProcedures = (int) (clone $baseQuery)->sum('total_count');
            $totalRevenue = (float) (clone $baseQuery)->sum('total_revenue');
        } else {
            $baseQuery = $this->applyRelatedBranchScope(PlanItem::query(), 'treatmentPlan');
            $this->applyDateRange($baseQuery, 'plan_items.created_at');

            $totalProcedures = (clone $baseQuery)->count();
            $totalRevenue = (float) (clone $baseQuery)->sum('final_amount');
        }

        return [
            ['label' => 'Tổng số lượng thủ thuật', 'value' => number_format($totalProcedures)],
            ['label' => 'Tổng thực thu', 'value' => number_format($totalRevenue).' đ'],
        ];
    }

    protected function usesRevenueAggregate(): bool
    {
        [$from, $until] = $this->getDateRangeFromFilters();
        $scopeIds = $this->resolvedRevenueScopeIds();
        $decisionKey = md5(json_encode([
            'from' => $from,
            'until' => $until,
            'scope_ids' => $scopeIds,
        ]) ?: '');

        if (($this->usesRevenueAggregateDecision['key'] ?? null) === $decisionKey) {
            return (bool) $this->usesRevenueAggregateDecision['value'];
        }

        $usesAggregate = app(HotReportAggregateReadinessService::class)
            ->shouldUseRevenueAggregate($scopeIds, $from, $until);

        $this->usesRevenueAggregateDecision = [
            'key' => $decisionKey,
            'value' => $usesAggregate,
        ];

        return $usesAggregate;
    }

    /**
     * @return array<int, int>
     */
    protected function resolvedRevenueScopeIds(): array
    {
        $selectedBranchId = $this->rawSelectedBranchId();

        if ($this->isAdmin()) {
            return $selectedBranchId !== null
                ? [$selectedBranchId]
                : [0];
        }

        $branchIds = $this->resolvedVisibleBranchIds();

        return $branchIds ?? [];
    }
}
