<?php

namespace App\Filament\Resources\ReceiptsExpense\Tables;

use App\Models\ReceiptExpense;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;

class ReceiptsExpenseTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher_date')
                    ->label('Ngày lập')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Mã phiếu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('voucher_type')
                    ->label('Loại phiếu')
                    ->formatStateUsing(fn (ReceiptExpense $record): string => $record->getVoucherTypeLabel())
                    ->colors([
                        'success' => 'receipt',
                        'danger' => 'expense',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', locale: 'vi')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('payment_method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn (ReceiptExpense $record): string => $record->getPaymentMethodLabel())
                    ->colors([
                        'success' => 'cash',
                        'warning' => 'transfer',
                        'info' => 'card',
                        'gray' => 'other',
                    ]),
                Tables\Columns\TextColumn::make('payer_or_receiver')
                    ->label('Người nộp/nhận')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (ReceiptExpense $record): string => $record->getStatusLabel())
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'approved',
                        'info' => 'posted',
                        'gray' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('posted_at')
                    ->label('Hạch toán lúc')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('voucher_type')
                    ->label('Loại phiếu')
                    ->options([
                        'receipt' => 'Phiếu thu',
                        'expense' => 'Phiếu chi',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'approved' => 'Đã duyệt',
                        'posted' => 'Đã hạch toán',
                        'cancelled' => 'Đã hủy',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Phương thức')
                    ->options([
                        'cash' => 'Tiền mặt',
                        'transfer' => 'Chuyển khoản',
                        'card' => 'Thẻ',
                        'other' => 'Khác',
                    ]),
                Tables\Filters\SelectFilter::make('clinic_id')
                    ->label('Chi nhánh')
                    ->relationship('clinic', 'name')
                    ->searchable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->label('Sửa'),
                Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ReceiptExpense $record): bool => $record->status === 'draft')
                    ->action(function (ReceiptExpense $record): void {
                        $record->update(['status' => 'approved']);
                    }),
                Action::make('post')
                    ->label('Hạch toán')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('info')
                    ->visible(fn (ReceiptExpense $record): bool => in_array($record->status, ['draft', 'approved'], true))
                    ->requiresConfirmation()
                    ->action(function (ReceiptExpense $record): void {
                        $record->update([
                            'status' => 'posted',
                            'posted_at' => now(),
                            'posted_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->defaultSort('voucher_date', 'desc')
            ->emptyStateHeading('Chưa có phiếu thu/chi')
            ->emptyStateDescription('Tạo phiếu thu/chi đầu tiên để theo dõi thu chi.');
    }
}
