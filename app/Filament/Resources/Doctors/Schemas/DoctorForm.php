<?php

namespace App\Filament\Resources\Doctors\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class DoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->relationship('branch', 'name')
                    ->label('Chi nhánh')
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->label('Tên bác sĩ')
                    ->required(),
                Forms\Components\TextInput::make('specialization')
                    ->label('Chuyên môn'),
                Forms\Components\TextInput::make('phone')
                    ->label('Điện thoại'),
            ]);
    }
}
