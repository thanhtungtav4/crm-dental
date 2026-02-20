<?php

namespace App\Filament\Resources\ReceiptsExpense\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptsExpenseForm
{
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
                            ->preload(),
                        Select::make('voucher_type')
                            ->label('Loại phiếu')
                            ->options([
                                'receipt' => 'Phiếu thu',
                                'expense' => 'Phiếu chi',
                            ])
                            ->default('receipt')
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
                            ->suffix('đ'),
                        Select::make('payment_method')
                            ->label('Phương thức')
                            ->options([
                                'cash' => 'Tiền mặt',
                                'transfer' => 'Chuyển khoản',
                                'card' => 'Thẻ',
                                'other' => 'Khác',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false),
                        TextInput::make('payer_or_receiver')
                            ->label('Người nộp/nhận')
                            ->maxLength(255),
                        Textarea::make('content')
                            ->label('Nội dung')
                            ->rows(3)
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
}
