<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Hóa đơn';

    protected static ?string $recordTitleAttribute = 'invoice_no';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_no')
                    ->label('Số hóa đơn')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary')
                    ->weight('bold')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.invoices.edit', ['record' => $record->id])),

                Tables\Columns\TextColumn::make('plan.title')
                    ->label('Kế hoạch')
                    ->limit(25)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Nháp',
                        'issued' => 'Đã xuất',
                        'partial' => 'Thanh toán một phần',
                        'paid' => 'Đã thanh toán',
                        'overdue' => 'Quá hạn',
                        'cancelled' => 'Đã hủy',
                        default => 'Không xác định',
                    })
                    ->colors([
                        'secondary' => 'draft',
                        'info' => 'issued',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'danger' => 'overdue',
                        'gray' => 'cancelled',
                    ]),

                Tables\Columns\BadgeColumn::make('invoice_exported')
                    ->label('Xuất HĐ')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Đã xuất' : 'Chưa xuất')
                    ->colors([
                        'success' => true,
                        'gray' => false,
                    ]),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND', locale: 'vi')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Đã thanh toán')
                    ->money('VND', locale: 'vi')
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Còn lại')
                    ->formatStateUsing(fn (Invoice $record) => number_format($record->calculateBalance(), 0, ',', '.').'đ')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Ngày xuất')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Hạn thanh toán')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->due_date && $record->due_date->isPast() && $record->status !== 'paid'
                            ? 'danger'
                            : null
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'issued' => 'Đã xuất',
                        'partial' => 'Thanh toán một phần',
                        'paid' => 'Đã thanh toán',
                        'overdue' => 'Quá hạn',
                        'cancelled' => 'Đã hủy',
                    ]),
            ])
            ->headerActions([
                Action::make('printLatestInvoice')
                    ->label('In hóa đơn')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getOwnerRecord()->invoices()->exists())
                    ->url(function (): string {
                        $invoice = $this->getOwnerRecord()->invoices()->latest('created_at')->first();

                        return $invoice ? route('invoices.print', $invoice) : '#';
                    })
                    ->openUrlInNewTab(),
            ])
            ->actions([
                EditAction::make()
                    ->label('')
                    ->tooltip('Sửa')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.invoices.edit', ['record' => $record->id])),
                Action::make('print')
                    ->label('')
                    ->tooltip('In')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => route('invoices.print', $record))
                    ->openUrlInNewTab(),

                Action::make('recordPayment')
                    ->label('')
                    ->tooltip('Ghi thanh toán')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (Invoice $record) => $record->status !== 'paid' && $record->status !== 'cancelled')
                    ->url(fn (Invoice $record): string => route('filament.admin.resources.payments.create', [
                        'invoice_id' => $record->id,
                    ])),
            ])
            ->emptyStateHeading('Chưa có hóa đơn')
            ->emptyStateDescription('Tạo hóa đơn đầu tiên cho bệnh nhân này')
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Tạo hóa đơn mới')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn () => route('filament.admin.resources.invoices.create', [
                        'patient_id' => $this->getOwnerRecord()->id,
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
