<?php

namespace App\Filament\Resources\Notes;

use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Filament\Resources\Notes\Schemas\NoteForm;
use App\Filament\Resources\Notes\Tables\NotesTable;
use App\Models\Note;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    public static function getNavigationLabel(): string
    {
        return 'Ghi chú';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý khách hàng';
    }

    protected static ?int $navigationSort = 22;

    public static function form(Schema $schema): Schema
    {
        return NoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotesTable::configure($table);
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

        return $query->where(function (Builder $innerQuery) use ($branchIds): void {
            $innerQuery->whereHas('patient', fn (Builder $patientQuery) => $patientQuery->whereIn('first_branch_id', $branchIds))
                ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->whereIn('branch_id', $branchIds));
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
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from sidebar; managed within Patient/Customer profiles
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
