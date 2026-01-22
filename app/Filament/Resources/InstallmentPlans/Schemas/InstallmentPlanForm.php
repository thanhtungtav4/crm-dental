<?php

namespace App\Filament\Resources\InstallmentPlans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Schema;

class InstallmentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                
                // ==================== SECTION 1: HÓA ĐƠN & BỆNH NHÂN ====================
                Section::make('📄 Hóa đơn & Bệnh nhân')
                    ->schema([
                        Select::make('invoice_id')
                            ->label('Hóa đơn')
                            ->relationship('invoice', 'invoice_no')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return $record->invoice_no . ' - ' . 
                                       $record->patient?->full_name . 
                                       ' (Tổng: ' . number_format($record->total_amount, 0, ',', '.') . 'đ)';
                            })
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $invoice = \App\Models\Invoice::find($state);
                                    if ($invoice) {
                                        $set('total_amount', $invoice->total_amount);
                                        $set('remaining_amount', $invoice->calculateBalance());
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        
                        Placeholder::make('patient_info')
                            ->label('Thông tin bệnh nhân')
                            ->content(function (Get $get) {
                                $invoiceId = $get('invoice_id');
                                if (!$invoiceId) {
                                    return 'Chọn hóa đơn để xem thông tin bệnh nhân';
                                }
                                
                                $invoice = \App\Models\Invoice::find($invoiceId);
                                if (!$invoice || !$invoice->patient) {
                                    return 'Không tìm thấy thông tin';
                                }
                                
                                $patient = $invoice->patient;
                                return new \Illuminate\Support\HtmlString("
                                    <div class='space-y-1'>
                                        <div><strong>Họ tên:</strong> {$patient->full_name}</div>
                                        <div><strong>Số điện thoại:</strong> {$patient->phone_number}</div>
                                        <div><strong>Email:</strong> " . ($patient->email ?? 'N/A') . "</div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ==================== SECTION 2: CẤU HÌNH TRẢ GÓP ====================
                Section::make('⚙️ Cấu hình trả góp')
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Tổng số tiền')
                            ->required()
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Tự động lấy từ hóa đơn'),
                        
                        Select::make('number_of_installments')
                            ->label('Số kỳ trả góp')
                            ->options([
                                3 => '3 kỳ (3 tháng)',
                                6 => '6 kỳ (6 tháng)',
                                9 => '9 kỳ (9 tháng)',
                                12 => '12 kỳ (12 tháng)',
                            ])
                            ->default(3)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $total = floatval($get('total_amount'));
                                $interestRate = floatval($get('interest_rate') ?? 0);
                                if ($total > 0 && $state > 0) {
                                    $interest = $total * ($interestRate / 100);
                                    $totalWithInterest = $total + $interest;
                                    $installmentAmount = $totalWithInterest / $state;
                                    $set('installment_amount', round($installmentAmount, 2));
                                }
                            })
                            ->native(false),
                        
                        Select::make('payment_frequency')
                            ->label('Tần suất thanh toán')
                            ->options([
                                'monthly' => '📅 Hàng tháng',
                                'weekly' => '📆 Hàng tuần',
                                'custom' => '⚙️ Tùy chỉnh',
                            ])
                            ->default('monthly')
                            ->required()
                            ->native(false),
                        
                        TextInput::make('interest_rate')
                            ->label('Lãi suất (%)')
                            ->numeric()
                            ->default(0)
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                $total = floatval($get('total_amount'));
                                $installments = intval($get('number_of_installments') ?? 3);
                                if ($total > 0 && $installments > 0) {
                                    $interest = $total * (floatval($state) / 100);
                                    $totalWithInterest = $total + $interest;
                                    $installmentAmount = $totalWithInterest / $installments;
                                    $set('installment_amount', round($installmentAmount, 2));
                                }
                            })
                            ->helperText('Để 0 nếu không tính lãi'),
                        
                        DatePicker::make('start_date')
                            ->label('Ngày bắt đầu')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now()->addDays(7))
                            ->minDate(now())
                            ->helperText('Ngày đến hạn kỳ đầu tiên'),
                        
                        TextInput::make('installment_amount')
                            ->label('Số tiền mỗi kỳ')
                            ->required()
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Tự động tính = (Tổng + Lãi) / Số kỳ'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ==================== SECTION 3: LỊCH TRẢ GÓP ====================
                Section::make('📅 Lịch trả góp')
                    ->schema([
                        Placeholder::make('schedule_info')
                            ->label('Thông tin lịch')
                            ->content(function (Get $get) {
                                $installments = $get('number_of_installments') ?? 3;
                                $amount = $get('installment_amount') ?? 0;
                                $frequency = $get('payment_frequency') ?? 'monthly';
                                $startDate = $get('start_date');
                                
                                $frequencyLabel = match($frequency) {
                                    'monthly' => 'hàng tháng',
                                    'weekly' => 'hàng tuần',
                                    'custom' => 'tùy chỉnh',
                                    default => 'N/A',
                                };
                                
                                $endDate = null;
                                if ($startDate) {
                                    $date = \Carbon\Carbon::parse($startDate);
                                    if ($frequency === 'monthly') {
                                        $endDate = $date->copy()->addMonths($installments - 1)->format('d/m/Y');
                                    } elseif ($frequency === 'weekly') {
                                        $endDate = $date->copy()->addWeeks($installments - 1)->format('d/m/Y');
                                    }
                                }
                                
                                return new \Illuminate\Support\HtmlString("
                                    <div class='space-y-2 text-sm'>
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Số kỳ:</span>
                                            <span class='font-bold'>{$installments} kỳ ({$frequencyLabel})</span>
                                        </div>
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Mỗi kỳ:</span>
                                            <span class='font-bold text-blue-600'>" . number_format($amount, 0, ',', '.') . "đ</span>
                                        </div>
                                        " . ($endDate ? "
                                        <div class='flex justify-between'>
                                            <span class='font-medium'>Kỳ cuối:</span>
                                            <span class='font-bold'>{$endDate}</span>
                                        </div>
                                        " : "") . "
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                        
                        Hidden::make('schedule')
                            ->default([]),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),

                // ==================== SECTION 4: THANH TOÁN ====================
                Section::make('💰 Thanh toán')
                    ->schema([
                        TextInput::make('paid_amount')
                            ->label('Đã thanh toán')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Tổng số tiền đã thanh toán'),
                        
                        TextInput::make('remaining_amount')
                            ->label('Còn lại')
                            ->numeric()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->disabled()
                            ->reactive()
                            ->afterStateHydrated(function (Set $set, Get $get, $state) {
                                $total = floatval($get('total_amount') ?? 0);
                                $paid = floatval($get('paid_amount') ?? 0);
                                $set('remaining_amount', $total - $paid);
                            })
                            ->helperText('Số tiền còn phải trả'),
                        
                        Select::make('status')
                            ->label('Trạng thái')
                            ->options([
                                'active' => '✅ Đang hoạt động',
                                'completed' => '🎉 Hoàn thành',
                                'defaulted' => '⚠️ Nợ quá hạn',
                                'cancelled' => '❌ Đã hủy',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // ==================== SECTION 5: GHI CHÚ ====================
                Section::make('📝 Ghi chú')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Thông tin thêm về kế hoạch trả góp')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
