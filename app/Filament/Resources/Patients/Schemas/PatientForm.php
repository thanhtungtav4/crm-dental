<?php

namespace App\Filament\Resources\Patients\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema(static::getFormSchema());
    }

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'full_name', fn($query) => $query->doesntHave('patient'))
                ->label('Khách hàng')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function ($state, $set) {
                    if (!$state) {
                        return;
                    }
                    $customer = \App\Models\Customer::find($state);
                    if (!$customer) {
                        return;
                    }
                    // Auto-fill patient name & phone from customer
                    if ($customer->full_name) {
                        $set('full_name', $customer->full_name);
                    }
                    if ($customer->phone) {
                        $set('phone', $customer->phone);
                    }
                    // Auto-fill email (editable field)
                    if ($customer->email) {
                        $set('email', $customer->email);
                    }
                })
                ->visibleOn('create')
                ->nullable(),

            Forms\Components\Placeholder::make('customer_readonly')
                ->label('Khách hàng')
                ->content(fn($record) => $record?->customer?->full_name)
                ->visibleOn('edit')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('patient_code')
                ->label('Mã bệnh nhân')
                ->helperText('Tự động sinh — không thể chỉnh sửa')
                ->maxLength(50)
                ->unique(table: 'patients', column: 'patient_code', ignoreRecord: true)
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit')
                ->nullable(),

            Forms\Components\Select::make('first_branch_id')
                ->relationship('branch', 'name')
                ->label('Chi nhánh')
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\TextInput::make('full_name')
                ->label('Họ và tên')
                ->required()
                ->maxLength(255),

            Forms\Components\DatePicker::make('birthday')
                ->label('Ngày sinh')
                ->nullable(),

            Forms\Components\Select::make('gender')
                ->label('Giới tính')
                ->options([
                    'male' => 'Nam',
                    'female' => 'Nữ',
                    'other' => 'Khác',
                ])
                ->nullable(),

            Forms\Components\TextInput::make('phone')
                ->label('Điện thoại')
                ->tel()
                ->maxLength(20)
                ->unique(table: 'patients', column: 'phone', ignoreRecord: true)
                ->nullable(),

            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->unique(table: 'patients', column: 'email', ignoreRecord: true)
                ->nullable(),

            Forms\Components\TextInput::make('address')
                ->label('Địa chỉ')
                ->maxLength(255)
                ->nullable()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('medical_history')
                ->label('Tiền sử bệnh')
                ->rows(3)
                ->nullable()
                ->columnSpanFull(),
        ];
    }
}
