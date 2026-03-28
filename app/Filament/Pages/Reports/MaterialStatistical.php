<?php

namespace App\Filament\Pages\Reports;

use App\Services\InventorySupplyReportReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class MaterialStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê vật tư';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'material-statistical';

    protected function getDateColumn(): ?string
    {
        return 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        return $this->inventorySupplyReports()
            ->materialInventoryQuery($this->resolvedVisibleBranchIds());
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
            TextColumn::make('category')
                ->label('Nhóm/Danh mục vật tư')
                ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '-')
                ->sortable(),
            TextColumn::make('sku')
                ->label('Mã vật tư')
                ->searchable(),
            TextColumn::make('name')
                ->label('Tên vật tư')
                ->searchable()
                ->wrap(),
            TextColumn::make('unit')
                ->label('Đơn vị'),
            TextColumn::make('stock_qty')
                ->label('Tồn cuối kỳ')
                ->numeric(),
            TextColumn::make('supplier.name')
                ->label('Nhà cung cấp')
                ->default('-'),
            TextColumn::make('updated_at')
                ->label('Ngày cập nhật')
                ->date('d/m/Y'),
        ];
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Nhóm/Danh mục vật tư', 'value' => fn ($record) => $record->category],
            ['label' => 'Mã vật tư', 'value' => fn ($record) => $record->sku],
            ['label' => 'Tên vật tư', 'value' => fn ($record) => $record->name],
            ['label' => 'Đơn vị', 'value' => fn ($record) => $record->unit],
            ['label' => 'Tồn cuối kỳ', 'value' => fn ($record) => $record->stock_qty],
            ['label' => 'Nhà cung cấp', 'value' => fn ($record) => $record->supplier?->name],
            ['label' => 'Ngày cập nhật', 'value' => fn ($record) => $record->updated_at],
        ];
    }

    public function getStats(): array
    {
        [$from, $until] = $this->getDateRangeFromFilters();
        $summary = $this->inventorySupplyReports()->materialInventorySummary(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
        );

        return [
            ['label' => 'Tổng vật tư', 'value' => number_format($summary['total_materials'])],
            ['label' => 'Vật tư dưới định mức', 'value' => number_format($summary['low_stock'])],
        ];
    }

    protected function inventorySupplyReports(): InventorySupplyReportReadModelService
    {
        return app(InventorySupplyReportReadModelService::class);
    }
}
