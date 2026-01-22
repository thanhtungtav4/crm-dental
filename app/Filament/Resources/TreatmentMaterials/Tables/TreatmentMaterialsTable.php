<?php

namespace App\Filament\Resources\TreatmentMaterials\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TreatmentMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.id')->label('Phiên'),
                TextColumn::make('material.name')->label('Vật tư')->searchable(),
                TextColumn::make('quantity')->label('SL')->numeric()->sortable(),
                TextColumn::make('cost')->label('Chi phí')->money('VND')->sortable(),
                TextColumn::make('user.name')->label('Người dùng')->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
