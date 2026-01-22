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
            ]);
    }
}
