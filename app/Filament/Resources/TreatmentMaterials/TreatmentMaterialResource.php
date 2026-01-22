<?php

namespace App\Filament\Resources\TreatmentMaterials;

use App\Filament\Resources\TreatmentMaterials\Pages\CreateTreatmentMaterial;
use App\Filament\Resources\TreatmentMaterials\Pages\EditTreatmentMaterial;
use App\Filament\Resources\TreatmentMaterials\Pages\ListTreatmentMaterials;
use App\Filament\Resources\TreatmentMaterials\Schemas\TreatmentMaterialForm;
use App\Filament\Resources\TreatmentMaterials\Tables\TreatmentMaterialsTable;
use App\Models\TreatmentMaterial;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TreatmentMaterialResource extends Resource
{
    protected static ?string $model = TreatmentMaterial::class;

    public static function getNavigationLabel(): string
    {
        return 'Vật tư sử dụng';
    }

    public static function getNavigationGroup(): ?string
    {
        return '4️⃣ Dịch vụ & Điều trị';
    }
    
    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return TreatmentMaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentMaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatmentMaterials::route('/'),
            'create' => CreateTreatmentMaterial::route('/create'),
            'edit' => EditTreatmentMaterial::route('/{record}/edit'),
        ];
    }
}
