<?php

namespace App\Filament\Resources\ZnsCampaigns\Pages;

use App\Filament\Resources\ZnsCampaigns\ZnsCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListZnsCampaigns extends ListRecords
{
    protected static string $resource = ZnsCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
