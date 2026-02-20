<?php

namespace App\Filament\Resources\MaterialBatches;

use App\Filament\Resources\MaterialBatches\Pages\CreateMaterialBatch;
use App\Filament\Resources\MaterialBatches\Pages\EditMaterialBatch;
use App\Filament\Resources\MaterialBatches\Pages\ListMaterialBatches;
use App\Filament\Resources\MaterialBatches\Schemas\MaterialBatchForm;
use App\Filament\Resources\MaterialBatches\Tables\MaterialBatchesTable;
use App\Models\MaterialBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialBatchResource extends Resource
{
    protected static ?string $model = MaterialBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Lô vật tư';

    protected static ?string $modelLabel = 'lô vật tư';

    protected static ?string $pluralModelLabel = 'Lô vật tư';

    protected static ?int $navigationSort = 42;
    
    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý kho';
    }

    public static function form(Schema $schema): Schema
    {
        return MaterialBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialBatchesTable::configure($table);
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
            'index' => ListMaterialBatches::route('/'),
            'create' => CreateMaterialBatch::route('/create'),
            'edit' => EditMaterialBatch::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
