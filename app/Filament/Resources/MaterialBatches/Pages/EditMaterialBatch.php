<?php

namespace App\Filament\Resources\MaterialBatches\Pages;

use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use App\Services\InventorySelectionAuthorizer;
use Filament\Resources\Pages\EditRecord;

class EditMaterialBatch extends EditRecord
{
    protected static string $resource = MaterialBatchResource::class;

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
        $data = app(InventorySelectionAuthorizer::class)->sanitizeMaterialBatchFormData(auth()->user(), $data, $this->record);

        unset($data['quantity'], $data['status']);

        return $data;
    }
}
