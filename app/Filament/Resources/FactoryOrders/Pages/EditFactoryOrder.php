<?php

namespace App\Filament\Resources\FactoryOrders\Pages;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFactoryOrder extends EditRecord
{
    protected static string $resource = FactoryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
