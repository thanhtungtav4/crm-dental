<?php

namespace App\Filament\Resources\MaterialIssueNotes;

use App\Filament\Resources\MaterialIssueNotes\Pages\CreateMaterialIssueNote;
use App\Filament\Resources\MaterialIssueNotes\Pages\EditMaterialIssueNote;
use App\Filament\Resources\MaterialIssueNotes\Pages\ListMaterialIssueNotes;
use App\Filament\Resources\MaterialIssueNotes\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\MaterialIssueNotes\Schemas\MaterialIssueNoteForm;
use App\Filament\Resources\MaterialIssueNotes\Tables\MaterialIssueNotesTable;
use App\Models\MaterialIssueNote;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MaterialIssueNoteResource extends Resource
{
    protected static ?string $model = MaterialIssueNote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $navigationLabel = 'Phiếu xuất vật tư';

    protected static string|UnitEnum|null $navigationGroup = 'Quản lý kho';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return MaterialIssueNoteForm::configure($schema)->columns(2);
    }

    public static function table(Table $table): Table
    {
        return MaterialIssueNotesTable::configure($table);
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
            'index' => ListMaterialIssueNotes::route('/'),
            'create' => CreateMaterialIssueNote::route('/create'),
            'edit' => EditMaterialIssueNote::route('/{record}/edit'),
        ];
    }
}
