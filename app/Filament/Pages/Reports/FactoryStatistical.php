<?php

namespace App\Filament\Pages\Reports;

use App\Models\Branch;
use App\Models\FactoryOrder;
use App\Services\InventorySupplyReportReadModelService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class FactoryStatistical extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Thống kê xưởng/labo';

    protected static string|\UnitEnum|null $navigationGroup = 'Báo cáo & thống kê';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'factory-statistical';

    protected function getDateColumn(): ?string
    {
        return 'created_at';
    }

    protected function getTableQuery(): Builder
    {
        return $this->inventorySupplyReports()->factoryOrderQuery(
            $this->resolvedVisibleBranchIds(),
            $this->selectedStatus(),
            $this->selectedSupplierId(),
        );
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('order_no')
                ->label('Mã lệnh')
                ->searchable(),
            TextColumn::make('patient.full_name')
                ->label('Bệnh nhân')
                ->searchable()
                ->default('-')
                ->wrap(),
            TextColumn::make('supplier.name')
                ->label('Nhà cung cấp')
                ->default('-')
                ->wrap(),
            TextColumn::make('branch.name')
                ->label('Chi nhánh')
                ->default('-'),
            TextColumn::make('doctor.name')
                ->label('Bác sĩ')
                ->default('-'),
            TextColumn::make('status')
                ->label('Trạng thái')
                ->badge()
                ->formatStateUsing(fn (string $state): string => FactoryOrder::statusOptions()[$state] ?? $state),
            TextColumn::make('items_count')
                ->label('Số item')
                ->numeric(),
            TextColumn::make('items_total_amount')
                ->label('Tổng giá trị')
                ->money('VND', divideBy: 1),
            TextColumn::make('due_at')
                ->label('Hẹn trả')
                ->dateTime('d/m/Y H:i')
                ->default('-'),
        ];
    }

    protected function getTableFilters(): array
    {
        return array_merge(parent::getTableFilters(), [
            SelectFilter::make('branch_id')
                ->label('Chi nhánh')
                ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all()),
            SelectFilter::make('status')
                ->label('Trạng thái')
                ->options(FactoryOrder::statusOptions()),
            SelectFilter::make('supplier_id')
                ->label('Nhà cung cấp')
                ->relationship(
                    name: 'supplier',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                )
                ->searchable()
                ->preload(),
        ]);
    }

    protected function getExportColumns(): array
    {
        return [
            ['label' => 'Mã lệnh', 'value' => fn ($record) => $record->order_no],
            ['label' => 'Bệnh nhân', 'value' => fn ($record) => $record->patient?->full_name],
            ['label' => 'Nhà cung cấp', 'value' => fn ($record) => $record->supplier?->name],
            ['label' => 'Chi nhánh', 'value' => fn ($record) => $record->branch?->name],
            ['label' => 'Bác sĩ', 'value' => fn ($record) => $record->doctor?->name],
            ['label' => 'Trạng thái', 'value' => fn ($record) => FactoryOrder::statusOptions()[$record->status] ?? $record->status],
            ['label' => 'Số item', 'value' => fn ($record) => $record->items_count],
            ['label' => 'Tổng giá trị', 'value' => fn ($record) => $record->items_total_amount],
            ['label' => 'Hẹn trả', 'value' => fn ($record) => $record->due_at],
        ];
    }

    public function getStats(): array
    {
        [$from, $until] = $this->getDateRangeFromFilters();
        $summary = $this->inventorySupplyReports()->factoryOrderSummary(
            $this->resolvedVisibleBranchIds(),
            $from,
            $until,
            $this->selectedStatus(),
            $this->selectedSupplierId(),
        );

        return [
            ['label' => 'Tổng lệnh labo', 'value' => number_format($summary['total_orders'])],
            ['label' => 'Đang xử lý', 'value' => number_format($summary['open_orders'])],
            ['label' => 'Đã giao', 'value' => number_format($summary['delivered_orders'])],
            ['label' => 'Tổng giá trị', 'value' => number_format($summary['total_value']).' đ'],
        ];
    }

    protected function selectedStatus(): ?string
    {
        $status = $this->getFilterValue('status');

        return filled($status) ? (string) $status : null;
    }

    protected function selectedSupplierId(): ?int
    {
        $supplierId = $this->getFilterValue('supplier_id');

        return filled($supplierId) ? (int) $supplierId : null;
    }

    protected function inventorySupplyReports(): InventorySupplyReportReadModelService
    {
        return app(InventorySupplyReportReadModelService::class);
    }

    protected function getFilterValue(string $filterName): mixed
    {
        return data_get($this->tableFilters ?? [], "{$filterName}.value");
    }
}
