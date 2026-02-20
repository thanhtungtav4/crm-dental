<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PatientPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Phiếu thu/hoàn';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Ngày lập phiếu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('direction')
                    ->label('Loại phiếu')
                    ->formatStateUsing(fn (Payment $record): string => $record->getDirectionLabel())
                    ->colors([
                        'success' => 'receipt',
                        'danger' => 'refund',
                    ]),

                Tables\Columns\BadgeColumn::make('method')
                    ->label('Hình thức thanh toán')
                    ->formatStateUsing(fn (Payment $record): string => $record->getMethodLabel())
                    ->color(fn (Payment $record): string => $record->getMethodBadgeColor()),

                Tables\Columns\TextColumn::make('receiver.name')
                    ->label('Người tạo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', locale: 'vi')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (Payment $record): string => $record->isRefund() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('note')
                    ->label('Nội dung')
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->label('Loại phiếu')
                    ->options([
                        'receipt' => 'Phiếu thu',
                        'refund' => 'Phiếu hoàn',
                    ]),

                Tables\Filters\SelectFilter::make('method')
                    ->label('Hình thức')
                    ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: false)),
            ])
            ->headerActions([
                Action::make('create_payment')
                    ->label('Phiếu thu')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->url(fn (): string => $this->getCreatePaymentUrl()),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Xem'),
                EditAction::make()
                    ->label('Sửa')
                    ->url(fn (Payment $record): string => route('filament.admin.resources.payments.edit', ['record' => $record->id])),
                Action::make('print')
                    ->label('In')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Payment $record): string => route('payments.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading('Chưa có phiếu thu/hoàn')
            ->emptyStateDescription('Tạo phiếu thu đầu tiên cho bệnh nhân này.')
            ->emptyStateActions([
                Action::make('empty_create_payment')
                    ->label('Tạo phiếu thu')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn (): string => $this->getCreatePaymentUrl()),
            ]);
    }

    protected function getCreatePaymentUrl(): string
    {
        $invoice = $this->getOwnerRecord()
            ->invoices()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->latest('created_at')
            ->first();

        if (!$invoice) {
            $invoice = $this->getOwnerRecord()->invoices()->latest('created_at')->first();
        }

        return route(
            'filament.admin.resources.payments.create',
            $invoice ? ['invoice_id' => $invoice->id] : []
        );
    }
}
