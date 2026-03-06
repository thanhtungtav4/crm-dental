<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Invoice;
use App\Services\FinanceActorAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([

                // ==================== SECTION 1: THÔNG TIN THANH TOÁN ====================
                Section::make('💰 Thông tin thanh toán')
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Hóa đơn')
                            ->relationship(
                                name: 'invoice',
                                titleAttribute: 'invoice_no',
                                modifyQueryUsing: fn (Builder $query): Builder => self::scopeInvoiceQueryForCurrentUser($query),
                            )
                            ->searchable()
                            ->preload()
                            ->default(fn () => request()->integer('invoice_id') ?: null)
                            ->required()
                            ->reactive()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record->invoice_no.' - '.
                                       $record->patient?->full_name.
                                       ' ('.number_format($record->total_amount, 0, ',', '.').'đ)';
                            })
                            ->columnSpanFull(),

                        TextInput::make('amount')
                            ->label('Số tiền thanh toán')
                            ->required()
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->afterStateHydrated(function ($state, callable $set) {
                                if ($state !== null) {
                                    $set('amount', abs((float) $state));
                                }
                            })
                            ->helperText('Nhập số tiền dương. Phiếu hoàn sẽ tự trừ vào công nợ.')
                            ->reactive(),

                        Select::make('direction')
                            ->label('Loại phiếu')
                            ->options(fn (): array => ClinicRuntimeSettings::paymentDirectionOptions())
                            ->default(fn (): string => ClinicRuntimeSettings::defaultPaymentDirection())
                            ->required()
                            ->reactive()
                            ->native(false),

                        Toggle::make('is_deposit')
                            ->label('Đánh dấu tiền cọc')
                            ->default(false)
                            ->visible(fn (Get $get) => $get('direction') === 'receipt' && ClinicRuntimeSettings::allowDeposit()),

                        Select::make('method')
                            ->label('Phương thức thanh toán')
                            ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true))
                            ->default('cash')
                            ->required()
                            ->reactive()
                            ->native(false),

                        DateTimePicker::make('paid_at')
                            ->label('Thời gian thanh toán')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ==================== SECTION 2: CHI TIẾT GIAO DỊCH ====================
                Section::make('📋 Chi tiết giao dịch')
                    ->schema([
                        TextInput::make('transaction_ref')
                            ->label('Mã giao dịch')
                            ->helperText('Mã tham chiếu từ ngân hàng/máy POS')
                            ->visible(fn (Get $get) => in_array($get('method'), ['card', 'transfer', 'vnpay']))
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? trim($state) : null)
                            ->unique(
                                table: 'payments',
                                column: 'transaction_ref',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                    ->where('invoice_id', (int) $get('invoice_id'))
                                    ->whereNotNull('transaction_ref'),
                            )
                            ->maxLength(255),

                        Select::make('payment_source')
                            ->label('Nguồn thanh toán')
                            ->options(fn (): array => ClinicRuntimeSettings::paymentSourceOptions(withEmoji: true))
                            ->default(fn (): string => ClinicRuntimeSettings::defaultPaymentSource())
                            ->required()
                            ->reactive()
                            ->native(false),

                        TextInput::make('insurance_claim_number')
                            ->label('Số hồ sơ bảo hiểm')
                            ->visible(fn (Get $get) => $get('payment_source') === 'insurance')
                            ->maxLength(255)
                            ->helperText('Mã hồ sơ yêu cầu bảo hiểm'),
                        Textarea::make('refund_reason')
                            ->label('Lý do hoàn tiền')
                            ->rows(2)
                            ->visible(fn (Get $get) => $get('direction') === 'refund')
                            ->required(fn (Get $get) => $get('direction') === 'refund')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // ==================== SECTION 3: NGƯỜI NHẬN & GHI CHÚ ====================
                Section::make('👤 Người nhận & Ghi chú')
                    ->schema([
                        Select::make('received_by')
                            ->label('Người nhận tiền')
                            ->options(fn (Get $get): array => app(FinanceActorAuthorizer::class)->assignableReceiverOptions(
                                actor: auth()->user(),
                                branchId: self::resolveInvoiceBranchId($get('invoice_id')),
                            ))
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->required(),

                        Textarea::make('note')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Ghi chú thêm về khoản thanh toán này')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),

                // ==================== SECTION 4: THỐNG KÊ HÓA ĐƠN (VIEW ONLY) ====================
                Section::make('📊 Thống kê hóa đơn')
                    ->schema([
                        Placeholder::make('invoice_info')
                            ->label('Thông tin hóa đơn')
                            ->content(function (Get $get) {
                                $invoiceId = $get('invoice_id');
                                if (! $invoiceId) {
                                    return 'Chọn hóa đơn để xem thông tin';
                                }

                                $invoice = \App\Models\Invoice::find($invoiceId);
                                if (! $invoice) {
                                    return 'Không tìm thấy hóa đơn';
                                }

                                $totalAmount = number_format($invoice->total_amount, 0, ',', '.');
                                $totalPaid = number_format($invoice->getTotalPaid(), 0, ',', '.');
                                $balance = number_format($invoice->calculateBalance(), 0, ',', '.');
                                $progress = round($invoice->getPaymentProgress(), 2);

                                return new \Illuminate\Support\HtmlString("
                                    <div class='space-y-2'>
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Tổng hóa đơn:</span>
                                            <span class='font-bold text-gray-900'>{$totalAmount}đ</span>
                                        </div>
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Đã thanh toán:</span>
                                            <span class='font-bold text-green-600'>{$totalPaid}đ</span>
                                        </div>
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Còn lại:</span>
                                            <span class='font-bold text-red-600'>{$balance}đ</span>
                                        </div>
                                        <div class='w-full bg-gray-200 rounded-full h-2.5 mt-3'>
                                            <div class='bg-green-600 h-2.5 rounded-full' style='width: {$progress}%'></div>
                                        </div>
                                        <div class='text-center text-sm text-gray-600'>
                                            Tiến độ thanh toán: {$progress}%
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($operation) => $operation === 'create'),
            ]);
    }

    protected static function scopeInvoiceQueryForCurrentUser(Builder $query): Builder
    {
        $authUser = BranchAccess::currentUser();

        if (! $authUser instanceof \App\Models\User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($authUser);
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $innerQuery) use ($branchIds): void {
            $innerQuery->whereIn('branch_id', $branchIds)
                ->orWhere(function (Builder $fallbackQuery) use ($branchIds): void {
                    $fallbackQuery->whereNull('branch_id')
                        ->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->whereIn('first_branch_id', $branchIds));
                });
        });
    }

    protected static function resolveInvoiceBranchId(mixed $invoiceId): ?int
    {
        if (! is_numeric($invoiceId)) {
            return null;
        }

        return Invoice::query()
            ->with('patient:id,first_branch_id')
            ->find((int) $invoiceId)
            ?->resolveBranchId();
    }
}
