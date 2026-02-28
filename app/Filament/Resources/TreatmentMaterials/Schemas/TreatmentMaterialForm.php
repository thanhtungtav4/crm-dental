<?php

namespace App\Filament\Resources\TreatmentMaterials\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;

class TreatmentMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Forms\Components\Select::make('treatment_session_id')
                    ->relationship('session', 'id')
                    ->label('Phiên điều trị')
                    ->required(),
                Forms\Components\Select::make('material_id')
                    ->relationship('material', 'name')
                    ->label('Vật tư')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->label('Số lượng')
                    ->required(),
                Forms\Components\TextInput::make('cost')
                    ->numeric()
                    ->label('Chi phí'),
                Forms\Components\Select::make('used_by')
                    ->relationship('user', 'name')
                    ->label('Người dùng'),
            ]);
    }
}
