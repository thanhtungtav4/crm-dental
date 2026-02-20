<?php

namespace App\Filament\Resources\PromotionGroups\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromotionGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Mã nhóm')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('name')
                    ->label('Tên nhóm')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customers_count')
                    ->label('Số lead')
                    ->counts('customers')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('patients_count')
                    ->label('Số bệnh nhân')
                    ->counts('patients')
                    ->badge()
                    ->color('success'),
                IconColumn::make('is_active')
                    ->label('Kích hoạt')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Cập nhật')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
