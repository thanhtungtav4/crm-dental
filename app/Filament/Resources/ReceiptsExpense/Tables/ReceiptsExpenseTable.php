<?php

namespace App\Filament\Resources\ReceiptsExpense\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Models\ReceiptExpense;
use App\Services\ReceiptExpenseWorkflowService;
use App\Support\BranchAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceiptsExpenseTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with(['patient:id,full_name,patient_code,first_branch_id', 'invoice:id,invoice_no,branch_id', 'clinic:id,name']);

                return BranchAccess::scopeQueryByAccessibleBranches($query, 'clinic_id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('voucher_date')
                    ->label('Ngày lập')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('voucher_code')
                    ->label('Mã phiếu')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (ReceiptExpense $record): ?string => auth()->user()?->can('update', $record)
                        ? ReceiptsExpenseResource::getUrl('edit', ['record' => $record])
                        : null),
                Tables\Columns\TextColumn::make('clinic.name')
                    ->label('Chi nhánh')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->description(fn (ReceiptExpense $record): string => $record->patient?->patient_code ? 'Mã BN: '.$record->patient->patient_code : 'Không gắn bệnh nhân')
                    ->url(fn (ReceiptExpense $record): ?string => $record->patient
                        && (auth()->user()?->can('view', $record->patient) ?? false)
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'payments'])
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invoice.invoice_no')
                    ->label('Hóa đơn')
                    ->searchable()
                    ->placeholder('Không gắn hóa đơn')
                    ->url(fn (ReceiptExpense $record): ?string => $record->invoice
                        && (auth()->user()?->can('view', $record->invoice) ?? false)
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                        : null)
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
                    ->relationship(
                        'clinic',
                        'name',
                        fn (Builder $query): Builder => BranchAccess::scopeBranchQueryForCurrentUser($query),
                    )
                    ->searchable(),
                Tables\Filters\SelectFilter::make('patient_id')
                    ->label('Bệnh nhân')
                    ->relationship(
                        'patient',
                        'full_name',
                        fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query, 'first_branch_id'),
                    )
                    ->searchable(),
                Tables\Filters\SelectFilter::make('invoice_id')
                    ->label('Hóa đơn')
                    ->relationship(
                        'invoice',
                        'invoice_no',
                        fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query),
                    )
                    ->searchable(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->label('Sửa'),
                Action::make('openPatient')
                    ->label('Hồ sơ BN')
                    ->icon('heroicon-o-user')
                    ->color('primary')
                    ->url(fn (ReceiptExpense $record): ?string => $record->patient
                        && (auth()->user()?->can('view', $record->patient) ?? false)
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'payments'])
                        : null)
                    ->visible(fn (ReceiptExpense $record): bool => $record->patient !== null
                        && (auth()->user()?->can('view', $record->patient) ?? false))
                    ->openUrlInNewTab(),
                Action::make('openInvoice')
                    ->label('Mở hóa đơn')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (ReceiptExpense $record): ?string => $record->invoice
                        && (auth()->user()?->can('view', $record->invoice) ?? false)
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                        : null)
                    ->visible(fn (ReceiptExpense $record): bool => $record->invoice !== null
                        && (auth()->user()?->can('view', $record->invoice) ?? false))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->label('Duyệt')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->successNotificationTitle('Đã duyệt phiếu thu/chi')
                    ->visible(fn (ReceiptExpense $record): bool => $record->status === ReceiptExpense::STATUS_DRAFT
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->authorize(fn (ReceiptExpense $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Textarea::make('reason')
                            ->label('Ghi chú duyệt')
                            ->rows(3),
                    ])
                    ->action(function (ReceiptExpense $record, array $data): void {
                        app(ReceiptExpenseWorkflowService::class)->approve(
                            receiptExpense: $record,
                            reason: $data['reason'] ?? null,
                        );
                    }),
                Action::make('post')
                    ->label('Hạch toán')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('info')
                    ->successNotificationTitle('Đã hạch toán phiếu thu/chi')
                    ->visible(fn (ReceiptExpense $record): bool => in_array($record->status, [
                        ReceiptExpense::STATUS_DRAFT,
                        ReceiptExpense::STATUS_APPROVED,
                    ], true)
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->authorize(fn (ReceiptExpense $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Textarea::make('reason')
                            ->label('Ghi chú hạch toán')
                            ->rows(3),
                    ])
                    ->action(function (ReceiptExpense $record, array $data): void {
                        app(ReceiptExpenseWorkflowService::class)->post(
                            receiptExpense: $record,
                            reason: $data['reason'] ?? null,
                        );
                    }),
            ])
            ->defaultSort('voucher_date', 'desc')
            ->emptyStateHeading('Chưa có phiếu thu/chi')
            ->emptyStateDescription('Tạo phiếu thu/chi đầu tiên để theo dõi thu chi.');
    }
}
