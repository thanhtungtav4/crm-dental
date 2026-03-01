<?php

namespace App\Filament\Resources\ZnsCampaigns;

use App\Filament\Resources\ZnsCampaigns\Pages\CreateZnsCampaign;
use App\Filament\Resources\ZnsCampaigns\Pages\EditZnsCampaign;
use App\Filament\Resources\ZnsCampaigns\Pages\ListZnsCampaigns;
use App\Filament\Resources\ZnsCampaigns\RelationManagers\DeliveriesRelationManager;
use App\Filament\Resources\ZnsCampaigns\Schemas\ZnsCampaignForm;
use App\Filament\Resources\ZnsCampaigns\Tables\ZnsCampaignsTable;
use App\Models\User;
use App\Models\ZnsCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ZnsCampaignResource extends Resource
{
    protected static ?string $model = ZnsCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'ZNS Campaign';

    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ZnsCampaignForm::configure($schema)->columns(2);
    }

    public static function table(Table $table): Table
    {
        return ZnsCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DeliveriesRelationManager::class,
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
            'index' => ListZnsCampaigns::route('/'),
            'create' => CreateZnsCampaign::route('/create'),
            'edit' => EditZnsCampaign::route('/{record}/edit'),
        ];
    }
}
