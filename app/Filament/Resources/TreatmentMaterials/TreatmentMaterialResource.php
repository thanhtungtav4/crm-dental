<?php

namespace App\Filament\Resources\TreatmentMaterials;

use App\Filament\Resources\TreatmentMaterials\Pages\CreateTreatmentMaterial;
use App\Filament\Resources\TreatmentMaterials\Pages\EditTreatmentMaterial;
use App\Filament\Resources\TreatmentMaterials\Pages\ListTreatmentMaterials;
use App\Filament\Resources\TreatmentMaterials\Schemas\TreatmentMaterialForm;
use App\Filament\Resources\TreatmentMaterials\Tables\TreatmentMaterialsTable;
use App\Models\TreatmentMaterial;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreatmentMaterialResource extends Resource
{
    protected static ?string $model = TreatmentMaterial::class;

    public static function getNavigationLabel(): string
    {
        return 'Vật tư sử dụng';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Dịch vụ & điều trị';
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = $authUser->accessibleBranchIds();

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('session.treatmentPlan', function (Builder $treatmentPlanQuery) use ($branchIds): void {
            $treatmentPlanQuery->whereIn('branch_id', $branchIds);
        });
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
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
