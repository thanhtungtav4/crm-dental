<?php

namespace App\Filament\Pages\Reports;

use App\Models\PlanItem;
use App\Services\HotReportAggregateReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class TrickGroupStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Tổng hợp theo thủ thuật';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'trick-group-statistical';

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
            return $this->hotReportAggregates()
                ->revenueCategoryBreakdownQuery($this->resolvedRevenueScopeIds());
        }

        $query = PlanItem::query()
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->selectRaw('services.category_id as category_id, service_categories.name as category_name, count(*) as total_count, sum(plan_items.final_amount) as total_revenue, max(plan_items.created_at) as created_at')
            ->groupBy('services.category_id', 'service_categories.name');

        return $this->applyRelatedBranchScope($query, 'treatmentPlan');
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => $this->branchFilterOptions()),
        ]);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('category_name')
                ->label('Nhóm thủ thuật')
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

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Nhóm thủ thuật', 'value' => fn ($record) => $record->category_name],
            ['label' => 'Số lượng thủ thuật', 'value' => fn ($record) => $record->total_count],
            ['label' => 'Doanh thu', 'value' => fn ($record) => $record->total_revenue],
        ];
    }

    public function getStats(): array
    {
        [$from, $until] = $this->getDateRangeFromFilters();

        if ($this->usesRevenueAggregate()) {
            $summary = $this->hotReportAggregates()->revenueSummary($this->resolvedRevenueScopeIds(), $from, $until);
            $totalProcedures = $summary['total_procedures'];
            $totalRevenue = $summary['total_revenue'];
        } else {
            $baseQuery = $this->applyRelatedBranchScope(PlanItem::query(), 'treatmentPlan');
            $this->applyDateRange($baseQuery, 'created_at');

            $totalProcedures = (clone $baseQuery)->count();
            $totalRevenue = (clone $baseQuery)->sum('final_amount');
        }

        return [
            ['label' => 'Tổng thủ thuật', 'value' => number_format($totalProcedures)],
            ['label' => 'Tổng doanh thu', 'value' => number_format($totalRevenue).' đ'],
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

        $usesAggregate = $this->hotReportAggregates()
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

    protected function hotReportAggregates(): HotReportAggregateReadModelService
    {
        return app(HotReportAggregateReadModelService::class);
    }
}
