<?php

namespace App\Filament\Resources\BranchLogs;

use App\Filament\Resources\BranchLogs\Pages\CreateBranchLog;
use App\Filament\Resources\BranchLogs\Pages\EditBranchLog;
use App\Filament\Resources\BranchLogs\Pages\ListBranchLogs;
use App\Filament\Resources\BranchLogs\Schemas\BranchLogForm;
use App\Filament\Resources\BranchLogs\Tables\BranchLogsTable;
use App\Models\BranchLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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
        return '3️⃣ Quản lý khách hàng';
    }
    
    protected static ?int $navigationSort = 23;

    public static function form(Schema $schema): Schema
    {
        return BranchLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchLogsTable::configure($table);
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
            'index' => ListBranchLogs::route('/'),
            'create' => CreateBranchLog::route('/create'),
            'edit' => EditBranchLog::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hidden from sidebar; surfaced within related profiles
        return false;
    }
}
