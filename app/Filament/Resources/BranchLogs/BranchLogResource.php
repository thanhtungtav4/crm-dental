<?php

namespace App\Filament\Resources\BranchLogs;

use App\Filament\Resources\BranchLogs\Pages\ListBranchLogs;
use App\Filament\Resources\BranchLogs\Tables\BranchLogsTable;
use App\Models\BranchLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BranchLogResource extends Resource
{
    protected static ?string $model = BranchLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    public static function getNavigationLabel(): string
    {
        return 'Chuyển chi nhánh';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý khách hàng';
    }

    protected static ?int $navigationSort = 23;

    public static function table(Table $table): Table
    {
        return BranchLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'fromBranch', 'toBranch', 'mover'])
            ->visibleTo(auth()->user());
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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
            'index' => ListBranchLogs::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from sidebar; surfaced within related profiles
        return false;
    }
}
