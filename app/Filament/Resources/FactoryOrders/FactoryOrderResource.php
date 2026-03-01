<?php

namespace App\Filament\Resources\FactoryOrders;

use App\Filament\Resources\FactoryOrders\Pages\CreateFactoryOrder;
use App\Filament\Resources\FactoryOrders\Pages\EditFactoryOrder;
use App\Filament\Resources\FactoryOrders\Pages\ListFactoryOrders;
use App\Filament\Resources\FactoryOrders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\FactoryOrders\Schemas\FactoryOrderForm;
use App\Filament\Resources\FactoryOrders\Tables\FactoryOrdersTable;
use App\Models\FactoryOrder;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FactoryOrderResource extends Resource
{
    protected static ?string $model = FactoryOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Lệnh labo';

    protected static string|UnitEnum|null $navigationGroup = 'Dịch vụ & điều trị';

    protected static ?int $navigationSort = 36;

    public static function form(Schema $schema): Schema
    {
        return FactoryOrderForm::configure($schema)->columns(2);
    }

    public static function table(Table $table): Table
    {
        return FactoryOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        return $query->branchAccessible();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFactoryOrders::route('/'),
            'create' => CreateFactoryOrder::route('/create'),
            'edit' => EditFactoryOrder::route('/{record}/edit'),
        ];
    }
}
