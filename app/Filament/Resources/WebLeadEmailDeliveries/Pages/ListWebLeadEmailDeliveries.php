<?php

namespace App\Filament\Resources\WebLeadEmailDeliveries\Pages;

use App\Filament\Resources\WebLeadEmailDeliveries\WebLeadEmailDeliveryResource;
use Filament\Resources\Pages\ListRecords;

class ListWebLeadEmailDeliveries extends ListRecords
{
    protected static string $resource = WebLeadEmailDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
