<?php

namespace App\Filament\Resources\PromotionGroups;

use App\Filament\Resources\PromotionGroups\Pages\CreatePromotionGroup;
use App\Filament\Resources\PromotionGroups\Pages\EditPromotionGroup;
use App\Filament\Resources\PromotionGroups\Pages\ListPromotionGroups;
use App\Filament\Resources\PromotionGroups\Schemas\PromotionGroupForm;
use App\Filament\Resources\PromotionGroups\Tables\PromotionGroupsTable;
use App\Models\PromotionGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromotionGroupResource extends Resource
{
    protected static ?string $model = PromotionGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGiftTop;

    protected static ?string $navigationLabel = 'Nhóm khuyến mãi';

    protected static ?string $modelLabel = 'Nhóm khuyến mãi';

    protected static ?string $pluralModelLabel = 'Nhóm khuyến mãi';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return 'Cài đặt hệ thống';
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotionGroups::route('/'),
            'create' => CreatePromotionGroup::route('/create'),
            'edit' => EditPromotionGroup::route('/{record}/edit'),
        ];
    }
}
