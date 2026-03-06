<?php

namespace App\Filament\Resources\MaterialBatches\Tables;

use App\Services\InventorySelectionAuthorizer;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaterialBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('material.name')
                    ->label('Vật tư')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn ($record) => $record->material?->getCategoryLabel() ?? ''),
                TextColumn::make('batch_number')
                    ->label('Số lô')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Đã sao chép số lô'),
                TextColumn::make('expiry_date')
                    ->label('🚨 Hạn sử dụng')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => match ($record->getExpiryStatusBadge()) {
                        'danger' => 'danger',
                        'warning' => 'warning',
                        'success' => 'success',
                        default => 'gray',
                    })
                    ->description(fn ($record) => $record->getDaysUntilExpiry().' ngày'),
                TextColumn::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($record) => $record->quantity > 0 ? 'success' : 'danger')
                    ->weight(fn ($record) => $record->quantity > 0 ? 'medium' : 'normal'),
                TextColumn::make('purchase_price')
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
                TextColumn::make('received_date')
                    ->label('Ngày nhận')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('supplier.name')
                    ->label('Nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Đang sử dụng',
                        'expired' => 'Đã hết hạn',
                        'recalled' => 'Thu hồi',
                        'depleted' => 'Đã hết',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'danger',
                        'recalled' => 'warning',
                        'depleted' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('createdBy.name')
                    ->label('Người tạo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Ngày xóa')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('expiring_soon')
                    ->label('Sắp hết hạn')
                    ->queries(
                        true: fn (Builder $query) => $query->where('expiry_date', '<=', now()->addDays(30))
                            ->where('status', 'active'),
                        false: fn (Builder $query) => $query->where('expiry_date', '>', now()->addDays(30))
                            ->orWhere('status', '!=', 'active'),
                    )
                    ->placeholder('Tất cả')
                    ->trueLabel('Chỉ lô sắp hết hạn (< 30 ngày)')
                    ->falseLabel('Không sắp hết hạn'),
                TernaryFilter::make('in_stock')
                    ->label('Còn hàng')
                    ->queries(
                        true: fn (Builder $query) => $query->where('quantity', '>', 0),
                        false: fn (Builder $query) => $query->where('quantity', '<=', 0),
                    )
                    ->placeholder('Tất cả')
                    ->trueLabel('Còn hàng')
                    ->falseLabel('Hết hàng'),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'active' => 'Đang sử dụng',
                        'expired' => 'Đã hết hạn',
                        'recalled' => 'Thu hồi',
                        'depleted' => 'Đã hết',
                    ])
                    ->placeholder('Tất cả trạng thái'),
                SelectFilter::make('material_id')
                    ->label('Vật tư')
                    ->relationship(
                        'material',
                        'name',
                        fn (Builder $query): Builder => app(InventorySelectionAuthorizer::class)->scopeMaterials($query, auth()->user()),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả vật tư'),
                SelectFilter::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tất cả nhà cung cấp'),
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
            ->defaultSort('expiry_date', 'asc');
    }
}
