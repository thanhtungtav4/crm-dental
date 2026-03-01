<?php

namespace App\Filament\Resources\PatientWallets\Tables;

use App\Models\PatientWallet;
use App\Services\PatientWalletService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatientWalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.patient_code')
                    ->label('Mã bệnh nhân')
                    ->searchable()
                    ->default('-'),
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Chi nhánh')
                    ->default('-'),
                TextColumn::make('balance')
                    ->label('Số dư')
                    ->money('VND', divideBy: 1)
                    ->sortable(),
                TextColumn::make('total_deposit')
                    ->label('Tổng cọc')
                    ->money('VND', divideBy: 1)
                    ->toggleable(),
                TextColumn::make('total_spent')
                    ->label('Đã dùng')
                    ->money('VND', divideBy: 1)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('adjust')
                    ->label('Điều chỉnh')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        TextInput::make('amount')
                            ->label('Số tiền điều chỉnh (+/-)')
                            ->numeric()
                            ->required(),
                        Textarea::make('note')
                            ->label('Lý do')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function (PatientWallet $record, array $data): void {
                        app(PatientWalletService::class)->adjustBalance(
                            wallet: $record,
                            amount: (float) $data['amount'],
                            note: (string) $data['note'],
                            actorId: auth()->id(),
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
