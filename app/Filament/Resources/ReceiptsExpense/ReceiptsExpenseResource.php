<?php

namespace App\Filament\Resources\ReceiptsExpense;

use App\Filament\Resources\ReceiptsExpense\Pages\CreateReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Pages\EditReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Pages\ListReceiptsExpense;
use App\Filament\Resources\ReceiptsExpense\Schemas\ReceiptsExpenseForm;
use App\Filament\Resources\ReceiptsExpense\Tables\ReceiptsExpenseTable;
use App\Models\ReceiptExpense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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
}
