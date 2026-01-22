<?php

namespace App\Filament\Resources\Billings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BillingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('treatmentPlan.title')->label('Kế hoạch'),
                TextColumn::make('amount')->label('Số tiền')->money('VND')->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
