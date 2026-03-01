<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\PlanItem;
use App\Models\ReportRevenueDailyAggregate;
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

    protected ?bool $usesRevenueAggregateCache = null;

    protected function getDateColumn(): ?string
    {
        return $this->usesRevenueAggregate()
            ? 'snapshot_date'
            : 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        if ($this->usesRevenueAggregate()) {
            $scopeId = $this->selectedBranchScopeId();

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
                ->where('revenue_daily.branch_scope_id', $scopeId)
                ->groupBy('revenue_daily.service_id');

            return $query;
        }

        return PlanItem::query()
            ->selectRaw('
                plan_items.service_id as service_id,
                COALESCE(services.name, CONCAT("Service #", plan_items.service_id)) as service_name,
                service_categories.name as category_name,
                COUNT(plan_items.id) as total_count,
                COALESCE(SUM(COALESCE(plan_items.final_amount, 0)), 0) as total_revenue
            ')
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->when(
                filled($this->getFilterValue('branch_id')),
                fn (Builder $query) => $query->whereHas(
                    'treatmentPlan',
                    fn (Builder $innerQuery) => $innerQuery->where('branch_id', (int) $this->getFilterValue('branch_id'))
                )
            )
            ->groupBy('plan_items.service_id', 'services.name', 'service_categories.name');
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
                ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all()),
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
            $baseQuery->where('branch_scope_id', $this->selectedBranchScopeId());

            $totalProcedures = (int) (clone $baseQuery)->sum('total_count');
            $totalRevenue = (float) (clone $baseQuery)->sum('total_revenue');
        } else {
            $baseQuery = PlanItem::query();
            $this->applyDateRange($baseQuery, 'created_at');

            $branchId = $this->getFilterValue('branch_id');
            if (filled($branchId)) {
                $baseQuery->whereHas(
                    'treatmentPlan',
                    fn (Builder $innerQuery) => $innerQuery->where('branch_id', (int) $branchId)
                );
            }

            $totalProcedures = (clone $baseQuery)->count();
            $totalRevenue = (float) (clone $baseQuery)->sum('final_amount');
        }

        return [
            ['label' => 'Tổng số lượng thủ thuật', 'value' => number_format($totalProcedures)],
            ['label' => 'Tổng thực thu', 'value' => number_format($totalRevenue).' đ'],
        ];
    }

    protected function getFilterValue(string $filterName): mixed
    {
        return data_get($this->tableFilters ?? [], "{$filterName}.value");
    }

    protected function usesRevenueAggregate(): bool
    {
        if ($this->usesRevenueAggregateCache !== null) {
            return $this->usesRevenueAggregateCache;
        }

        $this->usesRevenueAggregateCache = ReportRevenueDailyAggregate::query()->exists();

        return $this->usesRevenueAggregateCache;
    }

    protected function selectedBranchScopeId(): int
    {
        $branchId = $this->getFilterValue('branch_id');

        return filled($branchId)
            ? (int) $branchId
            : 0;
    }
}
