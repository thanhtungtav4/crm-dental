<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\ReceiptsExpense\ReceiptsExpenseResource;
use App\Models\Invoice;
use App\Services\InvoiceWorkflowService;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Số hoá đơn')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->patient?->full_name)
                    ->weight('bold')
                    ->url(fn ($record): string => InvoiceResource::getUrl('edit', ['record' => $record])),

                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->description(fn ($record): string => $record->patient?->patient_code ? 'Mã BN: '.$record->patient->patient_code : 'Không có mã')
                    ->url(fn ($record): ?string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'payments'])
                        : null)
                    ->toggleable(),

                TextColumn::make('plan.title')
                    ->label('Kế hoạch điều trị')
                    ->toggleable()
                    ->limit(30),

                TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_info')
                    ->label('Thanh toán')
                    ->formatStateUsing(function ($record) {
                        $paid = number_format($record->getTotalPaid(), 0, ',', '.');
                        $total = number_format($record->total_amount, 0, ',', '.');

                        return "{$paid}đ / {$total}đ";
                    })
                    ->description(function ($record) {
                        $progress = $record->getPaymentProgress();

                        return round($progress, 1).'% hoàn thành';
                    })
                    ->color(function ($record) {
                        $progress = $record->getPaymentProgress();
                        if ($progress >= 100) {
                            return 'success';
                        }
                        if ($progress >= 50) {
                            return 'info';
                        }
                        if ($progress > 0) {
                            return 'warning';
                        }

                        return 'gray';
                    })
                    ->weight(fn ($record) => $record->getTotalPaid() > 0 ? 'bold' : 'normal')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('paid_amount', $direction);
                    }),

                TextColumn::make('balance')
                    ->label('Còn lại')
                    ->formatStateUsing(fn ($record) => number_format($record->calculateBalance(), 0, ',', '.').'đ')
                    ->color(fn ($record) => $record->calculateBalance() > 0 ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->calculateBalance() > 0 ? 'bold' : 'normal')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->selectRaw('(total_amount - COALESCE(paid_amount, 0)) as balance')
                            ->orderBy('balance', $direction);
                    }),

                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($record) => $record->getPaymentStatusLabel())
                    ->color(fn ($record) => $record->getStatusBadgeColor())
                    ->icon(function ($record) {
                        return match ($record->getStatusBadgeColor()) {
                            'danger' => Heroicon::OutlinedExclamationCircle,
                            'success' => Heroicon::OutlinedCheckCircle,
                            'warning' => Heroicon::OutlinedClock,
                            'info' => Heroicon::OutlinedInformationCircle,
                            default => null,
                        };
                    }),

                BadgeColumn::make('invoice_exported')
                    ->label('Xuất HĐ')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Đã xuất' : 'Chưa xuất')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('due_date')
                    ->label('Ngày đến hạn')
                    ->date('d/m/Y')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        if (! $record->due_date) {
                            return '—';
                        }
                        $dueDate = \Carbon\Carbon::parse($record->due_date);
                        if ($record->isOverdue()) {
                            $days = $record->getDaysOverdue();

                            return '⚠️ '.$dueDate->format('d/m/Y')." (quá hạn {$days} ngày)";
                        }
                        $daysUntil = now()->diffInDays($dueDate, false);
                        if ($daysUntil <= 7 && $daysUntil >= 0) {
                            return '⏰ '.$dueDate->format('d/m/Y')." (còn {$daysUntil} ngày)";
                        }

                        return $dueDate->format('d/m/Y');
                    })
                    ->color(function ($record) {
                        if ($record->isOverdue()) {
                            return 'danger';
                        }
                        if ($record->due_date && now()->diffInDays($record->due_date, false) <= 7) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->toggleable(),

                TextColumn::make('payments_count')
                    ->label('Số lần TT')
                    ->counts('payments')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái thanh toán')
                    ->multiple()
                    ->options([
                        'draft' => '📝 Nháp',
                        'issued' => '📋 Đã phát hành',
                        'partial' => '⚠️ TT một phần',
                        'paid' => '✅ Đã thanh toán',
                        'overdue' => '🔴 Quá hạn',
                        'cancelled' => '❌ Đã hủy',
                    ]),

                SelectFilter::make('payment_progress')
                    ->label('Tiến độ thanh toán')
                    ->options([
                        'unpaid' => 'Chưa thanh toán (0%)',
                        'partial' => 'Đã thanh toán một phần (1-99%)',
                        'paid' => 'Đã thanh toán đủ (100%)',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'unpaid') {
                            return $query->where('paid_amount', 0);
                        }
                        if ($state['value'] === 'partial') {
                            return $query->whereRaw('paid_amount > 0 AND paid_amount < total_amount');
                        }
                        if ($state['value'] === 'paid') {
                            return $query->whereRaw('paid_amount >= total_amount');
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),

                EditAction::make()
                    ->label('Sửa'),

                Action::make('cancel')
                    ->label('Hủy hóa đơn')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')
                            ->label('Lý do hủy')
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->successNotificationTitle('Đã hủy hóa đơn')
                    ->action(function (Invoice $record, array $data): void {
                        app(InvoiceWorkflowService::class)->cancel($record, $data['reason'] ?? null);
                    })
                    ->visible(fn (Invoice $record): bool => $record->canBeCancelled()),

                Action::make('open_patient_profile')
                    ->label('Hồ sơ BN')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('primary')
                    ->url(fn ($record): ?string => $record->patient
                        ? PatientResource::getUrl('view', ['record' => $record->patient, 'tab' => 'payments'])
                        : null)
                    ->visible(fn ($record): bool => $record->patient !== null)
                    ->openUrlInNewTab(),

                Action::make('create_receipt_expense_voucher')
                    ->label('Phiếu thu/chi')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('gray')
                    ->url(fn ($record): string => ReceiptsExpenseResource::getUrl('create', [
                        'patient_id' => $record->patient_id,
                        'invoice_id' => $record->id,
                        'clinic_id' => $record->resolveBranchId(),
                        'voucher_type' => 'receipt',
                        'amount' => $record->total_amount,
                        'payment_method' => 'cash',
                        'payer_or_receiver' => $record->patient?->full_name,
                        'content' => 'Thu theo hóa đơn '.$record->invoice_no,
                    ]))
                    ->openUrlInNewTab(),

                Action::make('record_payment')
                    ->label('Thanh toán')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->minValue(0)
                            ->default(fn ($record) => $record->calculateBalance())
                            ->helperText(fn ($record) => 'Còn lại: '.number_format($record->calculateBalance(), 0, ',', '.').'đ'),

                        Select::make('method')
                            ->label('Phương thức')
                            ->required()
                            ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true))
                            ->default('cash')
                            ->native(false),

                        TextInput::make('transaction_ref')
                            ->label('Mã giao dịch')
                            ->maxLength(255)
                            ->visible(fn (callable $get) => in_array($get('method'), ['card', 'transfer', 'vnpay'], true))
                            ->helperText('Dùng để chống ghi nhận thanh toán trùng lặp'),

                        Select::make('direction')
                            ->label('Loại phiếu')
                            ->required()
                            ->options(fn (): array => ClinicRuntimeSettings::paymentDirectionOptions())
                            ->default(fn (): string => ClinicRuntimeSettings::defaultPaymentDirection())
                            ->native(false)
                            ->reactive(),

                        Select::make('payment_source')
                            ->label('Nguồn thanh toán')
                            ->options(fn (): array => ClinicRuntimeSettings::paymentSourceOptions(withEmoji: true))
                            ->default(fn (): string => ClinicRuntimeSettings::defaultPaymentSource())
                            ->native(false)
                            ->reactive(),

                        TextInput::make('insurance_claim_number')
                            ->label('Số hồ sơ bảo hiểm')
                            ->visible(fn (callable $get): bool => $get('payment_source') === 'insurance'),

                        TextInput::make('refund_reason')
                            ->label('Lý do hoàn')
                            ->visible(fn (callable $get) => $get('direction') === 'refund')
                            ->required(fn (callable $get) => $get('direction') === 'refund'),

                        DateTimePicker::make('paid_at')
                            ->label('Ngày thanh toán')
                            ->required()
                            ->default(now())
                            ->format('d/m/Y H:i')
                            ->native(false),
                    ])
                    ->action(function ($record, array $data) {
                        $direction = (string) ($data['direction'] ?? ClinicRuntimeSettings::defaultPaymentDirection());
                        $paymentSource = (string) ($data['payment_source'] ?? ClinicRuntimeSettings::defaultPaymentSource());

                        $payment = $record->recordPayment(
                            amount: (float) $data['amount'],
                            method: (string) $data['method'],
                            notes: 'Thanh toán hóa đơn '.$record->invoice_no,
                            paidAt: $data['paid_at'],
                            direction: $direction,
                            refundReason: $data['refund_reason'] ?? null,
                            transactionRef: $data['transaction_ref'] ?? null,
                            paymentSource: $paymentSource,
                            insuranceClaimNumber: $data['insurance_claim_number'] ?? null,
                            receivedBy: auth()->id(),
                        );

                        $isDuplicateRetry = filled($data['transaction_ref'] ?? null) && ! $payment->wasRecentlyCreated;
                        $title = $direction === 'refund'
                            ? 'Đã ghi nhận hoàn tiền'
                            : 'Thanh toán thành công';
                        $body = 'Đã ghi nhận '.number_format($data['amount'], 0, ',', '.').'đ';

                        if ($isDuplicateRetry) {
                            $title = 'Mã giao dịch đã tồn tại';
                            $body = 'Đã dùng bản ghi thanh toán hiện có, không tạo trùng.';
                        }

                        $notification = Notification::make()
                            ->title($title)
                            ->body($body);

                        if ($isDuplicateRetry) {
                            $notification->warning();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    })
                    ->visible(fn (Invoice $record): bool => $record->status !== Invoice::STATUS_CANCELLED && ($record->calculateBalance() > 0 || $record->hasPayments()))
                    ->modalWidth('md'),

                Action::make('view_payments')
                    ->label('Xem thanh toán')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.payments.index', [
                        'tableFilters' => ['invoice_id' => ['value' => $record->id]],
                    ]))
                    ->visible(fn ($record) => $record->hasPayments())
                    ->openUrlInNewTab(),

                Action::make('print')
                    ->label('In hóa đơn')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn ($record) => route('invoices.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Chưa có hóa đơn')
            ->emptyStateDescription('Tạo hóa đơn đầu tiên cho bệnh nhân')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
