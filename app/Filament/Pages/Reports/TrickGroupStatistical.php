<?php

namespace App\Filament\Pages\Reports;

use App\Models\PlanItem;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class TrickGroupStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Tổng hợp theo thủ thuật';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'trick-group-statistical';

    protected function getDateColumn(): ?string
    {
        return 'plan_items.created_at';
    }

    protected function getTableQuery(): Builder
    {
        return PlanItem::query()
            ->join('services', 'services.id', '=', 'plan_items.service_id')
            ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->selectRaw('services.category_id as category_id, service_categories.name as category_name, count(*) as total_count, sum(plan_items.final_amount) as total_revenue, max(plan_items.created_at) as created_at')
            ->groupBy('services.category_id', 'service_categories.name');
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
        $baseQuery = PlanItem::query();
        $this->applyDateRange($baseQuery, 'created_at');

        $totalProcedures = (clone $baseQuery)->count();
        $totalRevenue = (clone $baseQuery)->sum('final_amount');

        return [
            ['label' => 'Tổng thủ thuật', 'value' => number_format($totalProcedures)],
            ['label' => 'Tổng doanh thu', 'value' => number_format($totalRevenue) . ' đ'],
        ];
    }
}
