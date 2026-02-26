<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Models\Payment;
use App\Support\ClinicRuntimeSettings;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Lịch sử thanh toán';

    protected static ?string $modelLabel = 'thanh toán';

    protected static ?string $pluralModelLabel = 'thanh toán';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin thanh toán')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->minValue(0)
                            ->default(fn () => $this->getOwnerRecord()->calculateBalance())
                            ->helperText(fn () => 'Còn lại: '.number_format($this->getOwnerRecord()->calculateBalance(), 0, ',', '.').'đ'),

                        Select::make('direction')
                            ->label('Loại phiếu')
                            ->required()
                            ->options([
                                'receipt' => 'Phiếu thu',
                                'refund' => 'Phiếu hoàn',
                            ])
                            ->default('receipt')
                            ->native(false)
                            ->reactive(),

                        Toggle::make('is_deposit')
                            ->label('Đánh dấu tiền cọc')
                            ->default(false)
                            ->visible(fn ($get) => $get('direction') === 'receipt' && ClinicRuntimeSettings::allowDeposit()),

                        Select::make('method')
                            ->label('Phương thức')
                            ->required()
                            ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true))
                            ->default('cash')
                            ->native(false)
                            ->reactive()
                            ->columnSpan(1),

                        DateTimePicker::make('paid_at')
                            ->label('Ngày thanh toán')
                            ->required()
                            ->default(now())
                            ->format('d/m/Y H:i')
                            ->native(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Chi tiết giao dịch')
                    ->schema([
                        TextInput::make('transaction_ref')
                            ->label('Mã giao dịch')
                            ->maxLength(255)
                            ->visible(fn ($get) => in_array($get('method'), ['card', 'transfer', 'vnpay']))
                            ->helperText('Mã tham chiếu từ ngân hàng hoặc cổng thanh toán'),

                        Textarea::make('refund_reason')
                            ->label('Lý do hoàn')
                            ->rows(2)
                            ->visible(fn ($get) => $get('direction') === 'refund')
                            ->required(fn ($get) => $get('direction') === 'refund'),

                        Select::make('payment_source')
                            ->label('Nguồn thanh toán')
                            ->options([
                                'patient' => '👤 Bệnh nhân',
                                'insurance' => '🏥 Bảo hiểm',
                                'other' => '📄 Khác',
                            ])
                            ->default('patient')
                            ->native(false)
                            ->reactive(),

                        TextInput::make('insurance_claim_number')
                            ->label('Số hồ sơ bảo hiểm')
                            ->maxLength(255)
                            ->visible(fn ($get) => $get('payment_source') === 'insurance'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Section::make('Người nhận & Ghi chú')
                    ->schema([
                        Select::make('received_by')
                            ->label('Người nhận')
                            ->relationship('receiver', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id()),

                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Hidden::make('invoice_id')
                    ->default(fn () => $this->getOwnerRecord()->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->weight('bold')
                    ->color(fn ($record) => $record->direction === 'refund' ? 'danger' : $record->getMethodBadgeColor())
                    ->sortable(),

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

                BadgeColumn::make('method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn ($record) => $record->getMethodLabel())
                    ->icon(fn ($record) => $record->getMethodIcon())
                    ->color(fn ($record) => $record->getMethodBadgeColor()),

                BadgeColumn::make('payment_source')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($record) => $record->getSourceLabel())
                    ->color(fn ($record) => $record->getSourceBadgeColor())
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label('Ngày thanh toán')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->diffForHumans()),

                TextColumn::make('receiver.name')
                    ->label('Người nhận')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transaction_ref')
                    ->label('Mã GD')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('note')
                    ->label('Ghi chú')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('refund_reason')
                    ->label('Lý do hoàn')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Phương thức')
                    ->multiple()
                    ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true)),

                SelectFilter::make('payment_source')
                    ->label('Nguồn')
                    ->multiple()
                    ->options([
                        'patient' => '👤 Bệnh nhân',
                        'insurance' => '🏥 Bảo hiểm',
                        'other' => '📄 Khác',
                    ]),

                SelectFilter::make('direction')
                    ->label('Loại phiếu')
                    ->multiple()
                    ->options([
                        'receipt' => 'Phiếu thu',
                        'refund' => 'Phiếu hoàn',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tạo thanh toán')
                    ->icon(Heroicon::OutlinedPlus)
                    ->using(function (array $data): Payment {
                        $invoice = $this->getOwnerRecord();
                        $transactionRef = filled($data['transaction_ref'] ?? null)
                            ? trim((string) $data['transaction_ref'])
                            : null;

                        if ($transactionRef) {
                            $existingPayment = Payment::query()
                                ->where('invoice_id', (int) $data['invoice_id'])
                                ->where('transaction_ref', $transactionRef)
                                ->first();

                            if ($existingPayment) {
                                Notification::make()
                                    ->warning()
                                    ->title('Mã giao dịch đã tồn tại')
                                    ->body('Đã dùng bản ghi thanh toán hiện có để tránh ghi trùng.')
                                    ->send();

                                return $existingPayment;
                            }
                        }

                        return $invoice->recordPayment(
                            amount: (float) ($data['amount'] ?? 0),
                            method: (string) ($data['method'] ?? 'cash'),
                            notes: $data['note'] ?? null,
                            paidAt: $data['paid_at'] ?? now(),
                            direction: (string) ($data['direction'] ?? 'receipt'),
                            refundReason: $data['refund_reason'] ?? null,
                            transactionRef: $transactionRef,
                            paymentSource: (string) ($data['payment_source'] ?? 'patient'),
                            insuranceClaimNumber: $data['insurance_claim_number'] ?? null,
                            receivedBy: $data['received_by'] ?? auth()->id(),
                            reversalOfId: null,
                            isDeposit: (bool) ($data['is_deposit'] ?? false),
                        );
                    })
                    ->after(function () {
                        $this->getOwnerRecord()->updatePaidAmount();
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                \Filament\Actions\Action::make('refund')
                    ->label('Hoàn')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Payment $record): bool => $record->canReverse())
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
                \Filament\Actions\Action::make('print')
                    ->label('In')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn ($record) => route('payments.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->emptyStateHeading('Chưa có thanh toán')
            ->emptyStateDescription('Tạo thanh toán đầu tiên cho hóa đơn này')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->heading(function () {
                $record = $this->getOwnerRecord();
                $paid = number_format($record->getTotalPaid(), 0, ',', '.');
                $total = number_format($record->total_amount, 0, ',', '.');
                $balance = number_format($record->calculateBalance(), 0, ',', '.');
                $progress = round($record->getPaymentProgress(), 1);

                return "Lịch sử thanh toán • Đã thu: {$paid}đ / {$total}đ ({$progress}%) • Còn lại: {$balance}đ";
            });
    }
}
