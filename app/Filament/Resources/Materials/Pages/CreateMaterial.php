<?php

namespace App\Filament\Resources\Materials\Pages;

use App\Filament\Resources\Materials\MaterialResource;
use App\Services\InventorySelectionAuthorizer;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterial extends CreateRecord
{
    protected static string $resource = MaterialResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = app(InventorySelectionAuthorizer::class)->sanitizeMaterialFormData(auth()->user(), $data);

        unset($data['stock_qty']);

        return $data;
    }
}
