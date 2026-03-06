<?php

namespace App\Filament\Resources\FactoryOrders\Pages;

use App\Filament\Resources\FactoryOrders\FactoryOrderResource;
use App\Services\FactoryOrderAuthorizer;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFactoryOrder extends EditRecord
{
    protected static string $resource = FactoryOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(FactoryOrderAuthorizer::class)->sanitizeFactoryOrderData(
            actor: auth()->user(),
            data: $data,
            record: $this->getRecord(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
