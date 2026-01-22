<?php

namespace App\Filament\Resources\Patients\Relations;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PatientBranchLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'branchLogs';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return 'Lịch sử chuyển chi nhánh';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fromBranch.name')->label('Từ chi nhánh'),
                Tables\Columns\TextColumn::make('toBranch.name')->label('Đến chi nhánh'),
                Tables\Columns\TextColumn::make('mover.name')->label('Người thực hiện'),
                Tables\Columns\TextColumn::make('note')->label('Ghi chú')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Thời gian')->dateTime()->sortable(),
            ])
            ->actions([])
            ->headerActions([])
            ->emptyStateHeading('Chưa có lịch sử')
            ->emptyStateDescription('Sẽ tự ghi log khi chuyển chi nhánh.');
    }
}
