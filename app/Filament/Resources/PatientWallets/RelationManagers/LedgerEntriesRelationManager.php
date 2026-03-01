<?php

namespace App\Filament\Resources\PatientWallets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Sổ quỹ ví';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_no')
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Tham chiếu')
                    ->default('-')
                    ->searchable(),
                BadgeColumn::make('entry_type')
                    ->label('Loại')
                    ->colors([
                        'success' => 'deposit',
                        'warning' => 'spend',
                        'info' => 'refund',
                        'gray' => 'adjustment',
                        'danger' => 'reversal',
                    ]),
                BadgeColumn::make('direction')
                    ->label('Hướng')
                    ->formatStateUsing(fn (string $state): string => $state === 'credit' ? 'Cộng' : 'Trừ')
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', divideBy: 1),
                TextColumn::make('balance_before')
                    ->label('Số dư trước')
                    ->money('VND', divideBy: 1)
                    ->toggleable(),
                TextColumn::make('balance_after')
                    ->label('Số dư sau')
                    ->money('VND', divideBy: 1),
                TextColumn::make('created_at')
                    ->label('Thời gian')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
