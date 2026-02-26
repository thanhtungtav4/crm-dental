<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Mã chi nhánh')
                    ->helperText('Tự động sinh — không thể chỉnh sửa')
                    ->maxLength(50)
                    ->unique(table: 'branches', column: 'code', ignoreRecord: true)
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->nullable(),

                Forms\Components\TextInput::make('name')
                    ->label('Tên chi nhánh')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->label('Điện thoại')
                    ->tel()
                    ->maxLength(20)
                    ->nullable(),

                Forms\Components\Select::make('manager_id')
                    ->relationship('manager', 'name')
                    ->label('Quản lý')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Textarea::make('address')
                    ->label('Địa chỉ')
                    ->rows(3)
                    ->columnSpanFull()
                    ->nullable(),

                Forms\Components\Toggle::make('active')
                    ->label('Kích hoạt')
                    ->default(true)
                    ->inline(false)
                    ->columnSpanFull(),

                Forms\Components\Section::make('Chính sách overbooking')
                    ->relationship('overbookingPolicy')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Cho phép overbooking')
                            ->default(false),
                        Forms\Components\TextInput::make('max_parallel_per_doctor')
                            ->label('Số lịch song song tối đa / bác sĩ')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->default(1)
                            ->required(),
                        Forms\Components\Toggle::make('require_override_reason')
                            ->label('Bắt buộc lý do override')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
