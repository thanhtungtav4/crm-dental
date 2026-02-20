<?php

namespace App\Filament\Resources\PromotionGroups\Pages;

use App\Filament\Resources\PromotionGroups\PromotionGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotionGroup extends EditRecord
{
    protected static string $resource = PromotionGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
