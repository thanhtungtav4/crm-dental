<?php

namespace App\Filament\Resources\TreatmentSessions;

use App\Filament\Resources\TreatmentSessions\Pages\CreateTreatmentSession;
use App\Filament\Resources\TreatmentSessions\Pages\EditTreatmentSession;
use App\Filament\Resources\TreatmentSessions\Pages\ListTreatmentSessions;
use App\Filament\Resources\TreatmentSessions\Schemas\TreatmentSessionForm;
use App\Filament\Resources\TreatmentSessions\Tables\TreatmentSessionsTable;
use App\Models\TreatmentSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TreatmentSessionResource extends Resource
{
    protected static ?string $model = TreatmentSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    public static function getNavigationLabel(): string
    {
        return 'Phiên điều trị';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Hoạt động hàng ngày';
    }
    
    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TreatmentSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreatmentSessionsTable::configure($table);
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
            'index' => ListTreatmentSessions::route('/'),
            'create' => CreateTreatmentSession::route('/create'),
            'edit' => EditTreatmentSession::route('/{record}/edit'),
        ];
    }
}
