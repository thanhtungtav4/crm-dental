<?php

namespace App\Filament\Resources\Branches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã chi nhánh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Tên chi nhánh')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->searchable(),
                TextColumn::make('manager.name')
                    ->label('Quản lý')
                    ->toggleable(),
                IconColumn::make('active')
                    ->label('Kích hoạt')
                    ->boolean(),
                IconColumn::make('overbookingPolicy.is_enabled')
                    ->label('Overbooking')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('overbookingPolicy.max_parallel_per_doctor')
                    ->label('Slot song song tối đa')
                    ->default('1')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
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
            ]);
    }
}
