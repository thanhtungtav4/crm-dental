<?php

namespace App\Filament\Resources\ServiceCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label('Tên danh mục')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->color ?? 'gray'),
                TextColumn::make('parent.name')
                    ->label('Danh mục cha')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->placeholder('Cấp 1'),
                TextColumn::make('services_count')
                    ->label('Số dịch vụ')
                    ->counts('services')
                    ->sortable(),
                TextColumn::make('icon')
                    ->label('Icon')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Kích hoạt')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('STT')
                    ->numeric()
                    ->sortable(),
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
                SelectFilter::make('parent_id')
                    ->label('Danh mục cha')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('active')
                    ->label('Trạng thái')
                    ->placeholder('Tất cả')
                    ->trueLabel('Đang hoạt động')
                    ->falseLabel('Đã tắt'),
                TrashedFilter::make()
                    ->label('Đã xóa'),
            ])
            ->defaultSort('sort_order', 'asc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
