<?php

namespace App\Filament\Resources\TreatmentPlans;

use App\Filament\Resources\TreatmentPlans\Pages\CreateTreatmentPlan;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Filament\Resources\TreatmentPlans\Pages\ListTreatmentPlans;
use App\Filament\Resources\TreatmentPlans\Schemas\TreatmentPlanForm;
use App\Filament\Resources\TreatmentPlans\Tables\TreatmentPlansTable;
use App\Models\TreatmentPlan;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TreatmentPlanResource extends Resource
{
    protected static ?string $model = TreatmentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationLabel(): string
    {
        return 'Kế hoạch điều trị';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Hoạt động hàng ngày';
    }

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return TreatmentPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentPlansTable::configure($table);
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

        return $query->whereIn('branch_id', $branchIds);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PlanItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreatmentPlans::route('/'),
            'create' => CreateTreatmentPlan::route('/create'),
            'edit' => EditTreatmentPlan::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
