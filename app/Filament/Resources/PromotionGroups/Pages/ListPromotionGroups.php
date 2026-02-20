<?php

namespace App\Filament\Resources\PromotionGroups\Pages;

use App\Filament\Resources\PromotionGroups\PromotionGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromotionGroups extends ListRecords
{
    protected static string $resource = PromotionGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
