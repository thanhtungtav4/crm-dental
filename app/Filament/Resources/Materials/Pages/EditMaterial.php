<?php

namespace App\Filament\Resources\Materials\Pages;

use App\Filament\Resources\Materials\MaterialResource;
use App\Services\InventorySelectionAuthorizer;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = app(InventorySelectionAuthorizer::class)->sanitizeMaterialFormData(auth()->user(), $data);

        unset($data['stock_qty']);

        return $data;
    }
}
