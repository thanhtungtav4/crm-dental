<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Invoice number with patient info
                TextColumn::make('invoice.invoice_no')
                    ->label('Số hóa đơn')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->invoice?->patient?->full_name ?? 'N/A')
                    ->url(fn ($record): ?string => $record->invoice
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                        : null),

                // Patient name (toggleable)
                TextColumn::make('invoice.patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record): ?string => $record->invoice?->patient
                        ? PatientResource::getUrl('view', ['record' => $record->invoice->patient, 'tab' => 'payments'])
                        : null)
                    ->toggleable(isToggledHiddenByDefault: false),

                // Amount with color by method
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', divideBy: 1)
                    ->sortable()
                    ->color(fn ($record) => $record->direction === 'refund' ? 'danger' : $record->getMethodBadgeColor())
                    ->weight('bold')
                    ->description(fn ($record) => $record->getDirectionLabel()),

                BadgeColumn::make('direction')
                    ->label('Loại phiếu')
                    ->formatStateUsing(fn ($record) => $record->getDirectionLabel())
                    ->color(fn ($record) => $record->direction === 'refund' ? 'danger' : 'success'),

                BadgeColumn::make('is_deposit')
                    ->label('Phiếu cọc')
                    ->formatStateUsing(fn ($state) => $state ? 'Có' : 'Không')
                    ->colors([
                        'warning' => true,
                        'gray' => false,
                    ])
                    ->toggleable(),

                // Payment method badge with Vietnamese labels
                BadgeColumn::make('method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn ($record) => $record->getMethodLabel())
                    ->icon(fn ($record) => $record->getMethodIcon())
                    ->color(fn ($record) => $record->getMethodBadgeColor()),

                // Payment source badge
                BadgeColumn::make('payment_source')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($record) => $record->getSourceLabel())
                    ->color(fn ($record) => $record->getSourceBadgeColor())
                    ->toggleable(isToggledHiddenByDefault: false),

                // Paid date with Vietnamese format
                TextColumn::make('paid_at')
                    ->label('Thời gian TT')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Receiver name (toggleable)
                TextColumn::make('receiver.name')
                    ->label('Người nhận')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Transaction reference (toggleable)
                TextColumn::make('transaction_ref')
                    ->label('Mã GD')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('refund_reason')
                    ->label('Lý do hoàn')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reversal_of_id')
                    ->label('Phiếu gốc')
                    ->formatStateUsing(fn ($state) => $state ? '#'.$state : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reversed_at')
                    ->label('Đảo phiếu lúc')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reversedBy.name')
                    ->label('Đảo phiếu bởi')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Invoice status
                BadgeColumn::make('invoice.status')
                    ->label('TT Hóa đơn')
                    ->formatStateUsing(fn ($record) => match ($record->invoice?->status) {
                        'draft' => 'Nháp',
                        'issued' => 'Đã xuất',
                        'partial' => 'TT 1 phần',
                        'paid' => 'Đã TT',
                        'overdue' => 'Quá hạn',
                        'cancelled' => 'Đã hủy',
                        default => 'N/A',
                    })
                    ->color(fn ($record) => $record->invoice?->getStatusBadgeColor() ?? 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by payment method
                SelectFilter::make('method')
                    ->label('Phương thức')
                    ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true))
                    ->multiple(),

                // Filter by payment source
                SelectFilter::make('payment_source')
                    ->label('Nguồn thanh toán')
                    ->options(fn (): array => ClinicRuntimeSettings::paymentSourceOptions(withEmoji: true))
                    ->multiple(),

                SelectFilter::make('direction')
                    ->label('Loại phiếu')
                    ->options(fn (): array => ClinicRuntimeSettings::paymentDirectionOptions())
                    ->multiple(),

                // Filter by receiver
                SelectFilter::make('received_by')
                    ->label('Người nhận')
                    ->relationship('receiver', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('is_deposit')
                    ->label('Phiếu cọc')
                    ->options([
                        '1' => 'Có',
                        '0' => 'Không',
                    ]),

                // Filter by date range
                Filter::make('paid_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('paid_from')
                            ->label('Từ ngày'),
                        \Filament\Forms\Components\DatePicker::make('paid_until')
                            ->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '>=', $date),
                            )
                            ->when(
                                $data['paid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                Action::make('refund')
                    ->label('Hoàn')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->successNotificationTitle('Đã tạo phiếu hoàn tiền')
                    ->visible(fn (Payment $record): bool => $record->canReverse() && $record->invoice !== null)
                    ->form([
                        TextInput::make('amount')
                            ->label('Số tiền hoàn')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn (Payment $record): float => abs((float) $record->amount))
                            ->default(fn (Payment $record): float => abs((float) $record->amount)),
                        DateTimePicker::make('paid_at')
                            ->label('Ngày hoàn')
                            ->required()
                            ->default(now())
                            ->format('d/m/Y H:i')
                            ->native(false),
                        Textarea::make('refund_reason')
                            ->label('Lý do hoàn')
                            ->required()
                            ->rows(2),
                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Payment $record, array $data): void {
                        $invoice = $record->invoice;

                        if (! $invoice) {
                            return;
                        }

                        DB::transaction(function () use ($record, $invoice, $data): void {
                            $record->markReversed(auth()->id());

                            $invoice->recordPayment(
                                amount: (float) $data['amount'],
                                method: (string) $record->method,
                                notes: $data['note'] ?? null,
                                paidAt: $data['paid_at'] ?? now(),
                                direction: 'refund',
                                refundReason: $data['refund_reason'] ?? null,
                                transactionRef: null,
                                paymentSource: (string) $record->payment_source,
                                insuranceClaimNumber: $record->insurance_claim_number,
                                receivedBy: auth()->id(),
                                reversalOfId: $record->id
                            );
                        });
                    }),
                \Filament\Actions\Action::make('view_invoice')
                    ->label('Xem HĐ')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn ($record) => $record->invoice
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                        : null)
                    ->openUrlInNewTab(),
                Action::make('view_patient_profile')
                    ->label('Hồ sơ BN')
                    ->icon('heroicon-o-user')
                    ->color('primary')
                    ->url(fn ($record): ?string => $record->invoice?->patient
                        ? PatientResource::getUrl('view', ['record' => $record->invoice->patient, 'tab' => 'payments'])
                        : null)
                    ->visible(fn ($record): bool => $record->invoice?->patient !== null)
                    ->openUrlInNewTab(),
                Action::make('create_receipt_expense_voucher')
                    ->label('Phiếu thu/chi')
                    ->icon('heroicon-o-document-plus')
                    ->color('gray')
                    ->url(function ($record): string {
                        $isRefund = $record->direction === 'refund';
                        $voucherType = $isRefund ? 'expense' : 'receipt';
                        $invoiceNo = $record->invoice?->invoice_no ?? '-';
                        $voucherContent = $isRefund
                            ? 'Hoàn tiền theo phiếu #'.$record->id.' / hóa đơn '.$invoiceNo
                            : 'Thu tiền theo phiếu #'.$record->id.' / hóa đơn '.$invoiceNo;

                        return ReceiptsExpenseResource::getUrl('create', [
                            'patient_id' => $record->invoice?->patient_id,
                            'invoice_id' => $record->invoice_id,
                            'clinic_id' => $record->invoice?->resolveBranchId(),
                            'voucher_type' => $voucherType,
                            'amount' => abs((float) $record->amount),
                            'payment_method' => $record->method,
                            'payer_or_receiver' => $record->invoice?->patient?->full_name,
                            'content' => $voucherContent,
                        ]);
                    })
                    ->openUrlInNewTab(),
                \Filament\Actions\Action::make('print')
                    ->label('In phiếu')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('payments.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Chưa có thanh toán nào')
            ->emptyStateDescription('Tạo thanh toán mới bằng cách nhấn nút bên dưới')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
