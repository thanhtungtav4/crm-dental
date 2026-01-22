<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Tên dịch vụ')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('category.name')
                    ->label('Danh mục')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? 'gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_price')
                    ->label('Đơn giá')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Thời lượng')
                    ->suffix(' phút')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('doctor_commission_rate')
                    ->label('Hoa hồng BS')
                    ->suffix('%')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Tất cả'),
                IconColumn::make('tooth_specific')
                    ->label('Theo răng')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Kích hoạt')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('STT')
                    ->numeric()
                    ->sortable()
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
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Danh mục')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('branch_id')
                    ->label('Chi nhánh')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('active')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã tắt'),
                TernaryFilter::make('tooth_specific')
                    ->label('Loại dịch vụ')
                    ->placeholder('Tất cả')
                    ->trueLabel('Theo răng cụ thể')
                    ->falseLabel('Toàn bộ'),
            ])
            ->defaultSort('sort_order', 'asc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
