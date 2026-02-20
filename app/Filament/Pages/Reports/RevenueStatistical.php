<?php

namespace App\Filament\Pages\Reports;

use App\Models\PlanItem;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class RevenueStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Doanh thu phòng khám';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'revenue-statistical';

    protected function getDateColumn(): ?string
    {
        return 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        return PlanItem::query()
            ->selectRaw('service_id, count(*) as total_count, sum(final_amount) as total_revenue')
            ->with(['service.category'])
            ->groupBy('service_id');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('service.category.name')
                ->label('Nhóm thủ thuật')
                ->default('-')
                ->wrap(),
            TextColumn::make('service.name')
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

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Nhóm thủ thuật', 'value' => fn ($record) => $record->service?->category?->name],
            ['label' => 'Tên thủ thuật', 'value' => fn ($record) => $record->service?->name],
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
            ['label' => 'Tổng số lượng thủ thuật', 'value' => number_format($totalProcedures)],
            ['label' => 'Tổng thực thu', 'value' => number_format($totalRevenue) . ' đ'],
        ];
    }
}
