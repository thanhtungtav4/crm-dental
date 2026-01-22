<?php

namespace App\Filament\Resources\Materials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label('Vật tư')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->manufacturer ?? ''),
                TextColumn::make('category')
                    ->label('Danh mục')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getCategoryLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'medicine' => 'danger',
                        'consumable' => 'info',
                        'equipment' => 'warning',
                        'dental_material' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'medicine' => 'heroicon-o-beaker',
                        'consumable' => 'heroicon-o-cube',
                        'equipment' => 'heroicon-o-wrench-screwdriver',
                        'dental_material' => 'heroicon-o-sparkles',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('Đơn vị')
                    ->toggleable(),
                TextColumn::make('stock_qty')
                    ->label('Tồn kho')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(function ($record) {
                        if ($record->needsReorder()) return 'danger';
                        if ($record->isLowStock()) return 'warning';
                        return 'success';
                    })
                    ->weight(function ($record) {
                        if ($record->needsReorder() || $record->isLowStock()) return 'bold';
                        return 'normal';
                    })
                    ->description(fn ($record) => 
                        $record->needsReorder() ? '🔴 Cần đặt hàng' : 
                        ($record->isLowStock() ? '⚠️ Tồn kho thấp' : '')
                    ),
                TextColumn::make('cost_price')
                    ->label('Giá nhập')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->suffix(' đ')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sale_price')
                    ->label('Giá bán')
                    ->numeric(
                        decimalPlaces: 0,
                        decimalSeparator: ',',
                        thousandsSeparator: '.',
                    )
                    ->suffix(' đ')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('supplier.name')
                    ->label('Nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('storage_location')
                    ->label('Vị trí')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                TextColumn::make('batches_count')
                    ->label('Số lô')
                    ->counts('batches')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Danh mục')
                    ->options([
                        'medicine' => '💊 Thuốc',
                        'consumable' => '📦 Vật tư tiêu hao',
                        'equipment' => '🔧 Thiết bị',
                        'dental_material' => '🦷 Vật liệu nha khoa',
                    ])
                    ->placeholder('Tất cả danh mục'),
                TernaryFilter::make('low_stock')
                    ->label('Tồn kho thấp')
                    ->queries(
                        true: fn (Builder $query) => $query->whereColumn('stock_qty', '<=', 'min_stock'),
                        false: fn (Builder $query) => $query->whereColumn('stock_qty', '>', 'min_stock'),
                    )
                    ->placeholder('Tất cả')
                    ->trueLabel('Chỉ vật tư tồn kho thấp')
                    ->falseLabel('Đủ hàng'),
                TernaryFilter::make('need_reorder')
                    ->label('Cần đặt hàng')
                    ->queries(
                        true: fn (Builder $query) => $query->whereColumn('stock_qty', '<=', 'reorder_point')
                            ->whereNotNull('reorder_point'),
                        false: fn (Builder $query) => $query->whereColumn('stock_qty', '>', 'reorder_point')
                            ->orWhereNull('reorder_point'),
                    )
                    ->placeholder('Tất cả')
                    ->trueLabel('Cần đặt hàng ngay')
                    ->falseLabel('Không cần đặt hàng'),
                TernaryFilter::make('has_expiring_batches')
                    ->label('Có lô sắp hết hạn')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('batches', function ($query) {
                            $query->where('expiry_date', '<=', now()->addDays(30))
                                ->where('status', 'active');
                        }),
                        false: fn (Builder $query) => $query->whereDoesntHave('batches', function ($query) {
                            $query->where('expiry_date', '<=', now()->addDays(30))
                                ->where('status', 'active');
                        }),
                    )
                    ->placeholder('Tất cả')
                    ->trueLabel('Có lô sắp hết hạn')
                    ->falseLabel('Không có lô sắp hết hạn'),
                SelectFilter::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả nhà cung cấp'),
                SelectFilter::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả chi nhánh'),
                TrashedFilter::make()
                    ->label('Đã xóa')
                    ->placeholder('Không bao gồm đã xóa')
                    ->trueLabel('Chỉ hiển thị đã xóa')
                    ->falseLabel('Bao gồm đã xóa'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Sửa'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn'),
                    ForceDeleteBulkAction::make()
                        ->label('Xóa vĩnh viễn'),
                    RestoreBulkAction::make()
                        ->label('Khôi phục'),
                ]),
            ])
            ->defaultSort('sku', 'asc');
    }
}
