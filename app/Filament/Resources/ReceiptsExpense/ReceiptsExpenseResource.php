<?php

namespace App\Filament\Resources\ReceiptsExpense;

use App\Filament\Resources\ReceiptsExpense\Pages\CreateReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Pages\EditReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Pages\ListReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Schemas\ReceiptsExpenseForm;
use App\Filament\Resources\ReceiptsExpense\Tables\ReceiptsExpenseTable;
use App\Models\ReceiptExpense;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class ReceiptsExpenseResource extends Resource
{
    protected static ?string $model = ReceiptExpense::class;

    protected static ?string $slug = 'receipts-expense';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getNavigationLabel(): string
    {
        return 'Thu/chi';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tài chính';
    }

    protected static ?int $navigationSort = 13;

    public static function getModelLabel(): string
    {
        return 'Phiếu thu/chi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Phiếu thu/chi';
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptsExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptsExpenseTable::configure($table);
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

        return $query->whereIn('clinic_id', $branchIds);
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
            'index' => ListReceiptsExpense::route('/'),
            'create' => CreateReceiptsExpense::route('/create'),
            'edit' => EditReceiptsExpense::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasBackingTable() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return static::hasBackingTable() && parent::canAccess();
    }

    public static function hasBackingTable(): bool
    {
        return DatabaseSchema::hasTable('receipts_expense');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }
}
