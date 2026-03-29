<?php

namespace App\Filament\Pages\Reports;

use App\Services\HotReportAggregateReadModelService;
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

            return $this->hotReportAggregates()->revenueBreakdownQuery($scopeIds);
        }

        return $this->hotReportAggregates()->liveRevenueBreakdownQuery($this->resolvedVisibleBranchIds());
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
        [$from, $until] = $this->getDateRangeFromFilters();

        if ($this->usesRevenueAggregate()) {
            $scopeIds = $this->resolvedRevenueScopeIds();
            $summary = $this->hotReportAggregates()->revenueSummary($scopeIds, $from, $until);
            $totalProcedures = $summary['total_procedures'];
            $totalRevenue = $summary['total_revenue'];
        } else {
            $summary = $this->hotReportAggregates()->liveRevenueSummary(
                $this->resolvedVisibleBranchIds(),
                $from,
                $until,
            );
            $totalProcedures = $summary['total_procedures'];
            $totalRevenue = $summary['total_revenue'];
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

        $usesAggregate = $this->hotReportAggregates()
            ->shouldUseRevenueAggregate($scopeIds, $from, $until);

        $this->usesRevenueAggregateDecision = [
            'key' => $decisionKey,
            'value' => $usesAggregate,
        ];

        return $usesAggregate;
    }

    protected function hotReportAggregates(): HotReportAggregateReadModelService
    {
        return app(HotReportAggregateReadModelService::class);
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
