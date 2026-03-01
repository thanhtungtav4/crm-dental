<?php

namespace App\Filament\Resources\ReceiptsExpense\Schemas;

use App\Models\Invoice;
use App\Models\Patient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReceiptsExpenseForm
{
    private const PAYMENT_METHOD_OPTIONS = [
        'cash' => 'Tiền mặt',
        'transfer' => 'Chuyển khoản',
        'card' => 'Thẻ',
        'other' => 'Khác',
    ];

    private const VOUCHER_TYPE_OPTIONS = [
        'receipt' => 'Phiếu thu',
        'expense' => 'Phiếu chi',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Thông tin phiếu')
                    ->schema([
                        Select::make('clinic_id')
                            ->label('Chi nhánh')
                            ->relationship('clinic', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => request()->integer('clinic_id') ?: null),
                        Select::make('patient_id')
                            ->label('Bệnh nhân')
                            ->relationship('patient', 'full_name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => request()->integer('patient_id') ?: null)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $patientId = self::normalizeId($state);
                                if (! $patientId) {
                                    $set('invoice_id', null);

                                    return;
                                }

                                if (! self::invoiceBelongsToPatient($get('invoice_id'), $patientId)) {
                                    $set('invoice_id', null);
                                }

                                if (! is_numeric($get('clinic_id'))) {
                                    $branchId = Patient::query()
                                        ->whereKey($patientId)
                                        ->value('first_branch_id');

                                    if (is_numeric($branchId)) {
                                        $set('clinic_id', (int) $branchId);
                                    }
                                }

                                $patientName = Patient::query()
                                    ->whereKey($patientId)
                                    ->value('full_name');

                                if (filled($patientName)) {
                                    $set('payer_or_receiver', $patientName);
                                }
                            }),
                        Select::make('invoice_id')
                            ->label('Hóa đơn liên quan')
                            ->options(fn (Get $get): array => self::invoiceOptionsForPatient($get('patient_id')))
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => request()->integer('invoice_id') ?: null)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $invoiceId = self::normalizeId($state);
                                if (! $invoiceId) {
                                    return;
                                }

                                $invoice = Invoice::query()
                                    ->with('patient:id,full_name,first_branch_id')
                                    ->find($invoiceId);

                                if (! $invoice) {
                                    return;
                                }

                                $set('patient_id', $invoice->patient_id);

                                if (is_numeric($invoice->branch_id)) {
                                    $set('clinic_id', (int) $invoice->branch_id);
                                } elseif (is_numeric($invoice->patient?->first_branch_id)) {
                                    $set('clinic_id', (int) $invoice->patient->first_branch_id);
                                }

                                if (filled($invoice->patient?->full_name)) {
                                    $set('payer_or_receiver', $invoice->patient->full_name);
                                }
                            }),
                        Select::make('voucher_type')
                            ->label('Loại phiếu')
                            ->options(self::VOUCHER_TYPE_OPTIONS)
                            ->default(fn (): string => self::defaultVoucherType())
                            ->required()
                            ->native(false),
                        DatePicker::make('voucher_date')
                            ->label('Ngày lập phiếu')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        TextInput::make('voucher_code')
                            ->label('Mã phiếu')
                            ->maxLength(50),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Chi tiết')
                    ->schema([
                        TextInput::make('group_code')
                            ->label('Nhóm thu/chi')
                            ->maxLength(100),
                        TextInput::make('category_code')
                            ->label('Danh mục thu/chi')
                            ->maxLength(100),
                        TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->default(fn (): ?float => self::defaultAmount()),
                        Select::make('payment_method')
                            ->label('Phương thức')
                            ->options(self::PAYMENT_METHOD_OPTIONS)
                            ->default(fn (): string => self::defaultPaymentMethod())
                            ->required()
                            ->native(false),
                        TextInput::make('payer_or_receiver')
                            ->label('Người nộp/nhận')
                            ->maxLength(255)
                            ->default(fn (): ?string => request()->string('payer_or_receiver')->toString() ?: null),
                        Textarea::make('content')
                            ->label('Nội dung')
                            ->rows(3)
                            ->default(fn (): ?string => request()->string('content')->toString() ?: null)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Trạng thái')
                    ->schema([
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'draft' => 'Nháp',
                                'approved' => 'Đã duyệt',
                                'posted' => 'Đã hạch toán',
                                'cancelled' => 'Đã hủy',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                        DateTimePicker::make('posted_at')
                            ->label('Hạch toán lúc')
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false)
                            ->displayFormat('d/m/Y H:i'),
                        Select::make('posted_by')
                            ->label('Hạch toán bởi')
                            ->relationship('poster', 'name')
                            ->disabled()
                            ->dehydrated(false)
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function invoiceOptionsForPatient(mixed $patientId): array
    {
        $query = Invoice::query()
            ->select(['id', 'invoice_no', 'patient_id', 'total_amount', 'status'])
            ->with('patient:id,full_name');

        $normalizedPatientId = self::normalizeId($patientId);
        if ($normalizedPatientId) {
            $query->where('patient_id', $normalizedPatientId);
        }

        return $query
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->mapWithKeys(function (Invoice $invoice): array {
                $statusLabel = Invoice::STATUS_LABELS[$invoice->status] ?? strtoupper((string) $invoice->status);
                $label = sprintf(
                    '%s - %s - %s (%s)',
                    (string) $invoice->invoice_no,
                    (string) ($invoice->patient?->full_name ?? 'Không rõ bệnh nhân'),
                    number_format((float) $invoice->total_amount, 0, ',', '.').'đ',
                    $statusLabel,
                );

                return [(int) $invoice->id => $label];
            })
            ->all();
    }

    protected static function invoiceBelongsToPatient(mixed $invoiceId, int $patientId): bool
    {
        $normalizedInvoiceId = self::normalizeId($invoiceId);
        if (! $normalizedInvoiceId) {
            return true;
        }

        return Invoice::query()
            ->whereKey($normalizedInvoiceId)
            ->where('patient_id', $patientId)
            ->exists();
    }

    protected static function normalizeId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    protected static function defaultVoucherType(): string
    {
        $voucherType = request()->query('voucher_type');

        return in_array($voucherType, array_keys(self::VOUCHER_TYPE_OPTIONS), true)
            ? $voucherType
            : 'receipt';
    }

    protected static function defaultPaymentMethod(): string
    {
        $paymentMethod = request()->query('payment_method');

        return in_array($paymentMethod, array_keys(self::PAYMENT_METHOD_OPTIONS), true)
            ? $paymentMethod
            : 'cash';
    }

    protected static function defaultAmount(): ?float
    {
        $amount = request()->query('amount');

        return is_numeric($amount) ? round(max(0, (float) $amount), 2) : null;
    }
}
