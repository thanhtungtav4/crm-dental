<?php

namespace App\Filament\Resources\PlanItems;

use App\Filament\Resources\PlanItems\Pages\CreatePlanItem;
use App\Filament\Resources\PlanItems\Pages\EditPlanItem;
use App\Filament\Resources\PlanItems\Pages\ListPlanItems;
use App\Filament\Resources\PlanItems\Schemas\PlanItemForm;
use App\Filament\Resources\PlanItems\Tables\PlanItemsTable;
use App\Models\PlanItem;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PlanItemResource extends Resource
{
    protected static ?string $model = PlanItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationLabel(): string
    {
        return 'Hạng mục điều trị';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Dịch vụ & điều trị';
    }

    protected static ?int $navigationSort = 33;

    public static function form(Schema $schema): Schema
    {
        return PlanItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanItemsTable::configure($table);
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

        return $query->whereHas('treatmentPlan', function (Builder $treatmentPlanQuery) use ($branchIds): void {
            $treatmentPlanQuery->whereIn('branch_id', $branchIds);
        });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanItems::route('/'),
            'create' => CreatePlanItem::route('/create'),
            'edit' => EditPlanItem::route('/{record}/edit'),
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
