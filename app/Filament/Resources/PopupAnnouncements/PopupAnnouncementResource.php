<?php

namespace App\Filament\Resources\PopupAnnouncements;

use App\Filament\Resources\PopupAnnouncements\Pages\CreatePopupAnnouncement;
use App\Filament\Resources\PopupAnnouncements\Pages\EditPopupAnnouncement;
use App\Filament\Resources\PopupAnnouncements\Pages\ListPopupAnnouncements;
use App\Filament\Resources\PopupAnnouncements\Pages\ViewPopupAnnouncement;
use App\Filament\Resources\PopupAnnouncements\Schemas\PopupAnnouncementForm;
use App\Filament\Resources\PopupAnnouncements\Schemas\PopupAnnouncementInfolist;
use App\Filament\Resources\PopupAnnouncements\Tables\PopupAnnouncementsTable;
use App\Models\PopupAnnouncement;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PopupAnnouncementResource extends Resource
{
    protected static ?string $model = PopupAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Ứng dụng mở rộng';

    protected static ?string $navigationLabel = 'Popup nội bộ';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return PopupAnnouncementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PopupAnnouncementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PopupAnnouncementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
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
            'index' => ListPopupAnnouncements::route('/'),
            'create' => CreatePopupAnnouncement::route('/create'),
            'view' => ViewPopupAnnouncement::route('/{record}'),
            'edit' => EditPopupAnnouncement::route('/{record}/edit'),
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
