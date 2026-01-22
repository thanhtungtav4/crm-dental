<?php

namespace App\Filament\Resources\Doctors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Tên')->searchable(),
                TextColumn::make('specialization')->label('Chuyên môn')->searchable(),
                TextColumn::make('phone')->label('Điện thoại'),
                TextColumn::make('branch.name')->label('Chi nhánh')->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
