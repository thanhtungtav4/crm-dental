<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thông tin cơ bản')
                    ->schema([
                        TextInput::make('name')
                            ->label('Tên nhà cung cấp')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('code')
                            ->label('Mã NCC')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->alphaNum()
                            ->dehydrateStateUsing(fn(string $state): string => \Illuminate\Support\Str::upper($state))
                            ->placeholder('VD: NCC001')
                            ->columnSpan(1),
                        TextInput::make('tax_code')
                            ->label('Mã số thuế')
                            ->maxLength(50)
                            ->placeholder('VD: 0123456789')
                            ->columnSpan(1),
                        Toggle::make('active')
                            ->label('Đang hoạt động')
                            ->default(true)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Thông tin liên hệ')
                    ->schema([
                        TextInput::make('contact_person')
                            ->label('Người liên hệ')
                            ->maxLength(200)
                            ->placeholder('VD: Nguyễn Văn A')
                            ->columnSpan(1),
                        TextInput::make('phone')
                            ->label('Số điện thoại')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('VD: 0901234567')
                            ->columnSpan(1),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('VD: contact@supplier.com')
                            ->columnSpan(1),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('VD: https://supplier.com')
                            ->columnSpan(1),
                        Textarea::make('address')
                            ->label('Địa chỉ')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Địa chỉ đầy đủ của nhà cung cấp...'),
                    ])
                    ->columns(2),

                Section::make('Điều khoản thanh toán')
                    ->schema([
                        Select::make('payment_terms')
                            ->label('Hạn thanh toán')
                            ->options([
                                'cash' => 'Thanh toán ngay (Cash)',
                                'cod' => 'Thanh toán khi nhận hàng (COD)',
                                '7_days' => 'Thanh toán sau 7 ngày',
                                '15_days' => 'Thanh toán sau 15 ngày',
                                '30_days' => 'Thanh toán sau 30 ngày',
                                '60_days' => 'Thanh toán sau 60 ngày',
                                '90_days' => 'Thanh toán sau 90 ngày',
                            ])
                            ->default('30_days')
                            ->required()
                            ->columnSpan(1),
                        Textarea::make('notes')
                            ->label('Ghi chú')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Ghi chú về nhà cung cấp, điều khoản đặc biệt...'),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Thông tin hệ thống')
                    ->schema([
                        Placeholder::make('created_by_info')
                            ->label('Người tạo')
                            ->content(fn($record) => $record?->createdBy?->name ?? 'Chưa có')
                            ->columnSpan(1),
                        Placeholder::make('updated_by_info')
                            ->label('Người cập nhật gần nhất')
                            ->content(fn($record) => $record?->updatedBy?->name ?? 'Chưa có')
                            ->columnSpan(1),
                        Placeholder::make('created_at')
                            ->label('Ngày tạo')
                            ->content(fn($record) => $record?->created_at?->format('d/m/Y H:i') ?? 'Chưa có')
                            ->columnSpan(1),
                        Placeholder::make('updated_at')
                            ->label('Cập nhật lúc')
                            ->content(fn($record) => $record?->updated_at?->format('d/m/Y H:i') ?? 'Chưa có')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn($record) => $record !== null),
            ]);
    }
}
