<?php

namespace App\Filament\Resources\WebLeadEmailDeliveries;

use App\Filament\Resources\WebLeadEmailDeliveries\Pages\ListWebLeadEmailDeliveries;
use App\Filament\Resources\WebLeadEmailDeliveries\Pages\ViewWebLeadEmailDelivery;
use App\Filament\Resources\WebLeadEmailDeliveries\Schemas\WebLeadEmailDeliveryInfolist;
use App\Filament\Resources\WebLeadEmailDeliveries\Tables\WebLeadEmailDeliveriesTable;
use App\Models\WebLeadEmailDelivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebLeadEmailDeliveryResource extends Resource
{
    protected static ?string $model = WebLeadEmailDelivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function getNavigationLabel(): string
    {
        return 'Mail web lead';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Cài đặt hệ thống';
    }

    public static function getModelLabel(): string
    {
        return 'Mail web lead';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Mail web lead';
    }

    public static function infolist(Schema $schema): Schema
    {
        return WebLeadEmailDeliveryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebLeadEmailDeliveriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['webLeadIngestion', 'customer.branch', 'branch', 'recipientUser'])
            ->visibleTo(auth()->user());
    }

    public static function getRelations(): array
    {
        return [];
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
            'index' => ListWebLeadEmailDeliveries::route('/'),
            'view' => ViewWebLeadEmailDelivery::route('/{record}'),
        ];
    }
}
