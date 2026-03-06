<?php

namespace App\Filament\Resources\BranchLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable(),
                TextColumn::make('fromBranch.name')
                    ->label('Chuyển từ')
                    ->toggleable(),
                TextColumn::make('toBranch.name')
                    ->label('Sang chi nhánh')
                    ->toggleable(),
                TextColumn::make('mover.name')
                    ->label('Người chuyển')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }
}
