<?php

namespace App\Filament\Schemas;

use App\Support\ClinicRuntimeSettings;
use Filament\Forms;

class SharedSchemas
{
    /**
     * Common fields for Customer/Lead profile.
     * Used in Customer create and Appointment inline create.
     */
    public static function customerProfileFields(): array
    {
        return [
            Forms\Components\TextInput::make('full_name')
                ->label('Họ và tên')
                ->required()
                ->maxLength(255)
                ->placeholder('VD: Nguyễn Văn A')
                ->columnSpan(1),

            Forms\Components\TextInput::make('phone')
                ->label('Số điện thoại')
                ->tel()
                // Required status depends on context, but usually required for contact
                // We keep it flexible or make it required by default
                ->maxLength(20)
                ->placeholder('VD: 0901234567')
                ->unique('customers', 'phone', ignoreRecord: true) // Table is customers
                ->validationMessages([
                    'unique' => 'Số điện thoại này đã tồn tại.',
                ])
                ->columnSpan(1),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255)
                ->placeholder('VD: email@example.com')
                ->columnSpan(1),

            Forms\Components\DatePicker::make('birthday')
                ->label('Ngày sinh')
                ->maxDate(now())
                ->native(false)
                ->displayFormat('d/m/Y')
                ->columnSpan(1),

            Forms\Components\Select::make('gender')
                ->label('Giới tính')
                ->options(fn (): array => ClinicRuntimeSettings::genderOptions())
                ->default('male')
                ->columnSpan(1),

            Forms\Components\Textarea::make('address')
                ->label('Địa chỉ')
                ->rows(2)
                ->columnSpanFull()
                ->placeholder('Nhập địa chỉ chi tiết...'),
        ];
    }
}
