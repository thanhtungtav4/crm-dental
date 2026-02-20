<?php

namespace App\Filament\Resources\TreatmentSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TreatmentSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('treatmentPlan.patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable(),
                TextColumn::make('doctor.name')
                    ->label('Bác sĩ')
                    ->toggleable(),
                TextColumn::make('assistant.name')
                    ->label('Trợ thủ')
                    ->toggleable(),
                TextColumn::make('performed_at')
                    ->label('Thời gian')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->icon(fn (?string $state) => \App\Support\StatusBadge::icon($state))
                    ->color(fn (?string $state) => \App\Support\StatusBadge::color($state)),
            ])
            ->filters([
                //
            ])
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
