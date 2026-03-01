<?php

namespace App\Filament\Resources\PatientWallets;

use App\Filament\Resources\PatientWallets\Pages\EditPatientWallet;
use App\Filament\Resources\PatientWallets\Pages\ListPatientWallets;
use App\Filament\Resources\PatientWallets\RelationManagers\LedgerEntriesRelationManager;
use App\Filament\Resources\PatientWallets\Schemas\PatientWalletForm;
use App\Filament\Resources\PatientWallets\Tables\PatientWalletsTable;
use App\Models\PatientWallet;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PatientWalletResource extends Resource
{
    protected static ?string $model = PatientWallet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Ví bệnh nhân';

    protected static string|UnitEnum|null $navigationGroup = 'Tài chính';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return PatientWalletForm::configure($schema)->columns(2);
    }

    public static function table(Table $table): Table
    {
        return PatientWalletsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LedgerEntriesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['patient', 'branch']);
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        return $query->branchAccessible();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientWallets::route('/'),
            'edit' => EditPatientWallet::route('/{record}/edit'),
        ];
    }
}
