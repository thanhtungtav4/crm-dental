<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã NCC')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                TextColumn::make('name')
                    ->label('Tên nhà cung cấp')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->contact_person),
                TextColumn::make('phone')
                    ->label('Số ĐT')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),
                TextColumn::make('tax_code')
                    ->label('Mã số thuế')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_terms')
                    ->label('Hạn thanh toán')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Tiền mặt',
                        'cod' => 'COD',
                        '7_days' => '7 ngày',
                        '15_days' => '15 ngày',
                        '30_days' => '30 ngày',
                        '60_days' => '60 ngày',
                        '90_days' => '90 ngày',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash', 'cod' => 'success',
                        '7_days', '15_days' => 'info',
                        '30_days' => 'warning',
                        '60_days', '90_days' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('materials_count')
                    ->label('Số vật tư')
                    ->counts('materials')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('batches_count')
                    ->label('Số lô hàng')
                    ->counts('batches')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Hoạt động')
                    ->boolean(),
                TextColumn::make('createdBy.name')
                    ->label('Người tạo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Đã xóa')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã tắt'),
                SelectFilter::make('payment_terms')
                    ->label('Hạn thanh toán')
                    ->options([
                        'cash' => 'Tiền mặt',
                        'cod' => 'COD',
                        '7_days' => '7 ngày',
                        '15_days' => '15 ngày',
                        '30_days' => '30 ngày',
                        '60_days' => '60 ngày',
                        '90_days' => '90 ngày',
                    ]),
                TrashedFilter::make()
                    ->label('Đã xóa'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }
}
