<?php

namespace App\Filament\Resources\MaterialBatches\Pages;

use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use App\Services\InventorySelectionAuthorizer;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialBatch extends CreateRecord
{
    protected static string $resource = MaterialBatchResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(InventorySelectionAuthorizer::class)->sanitizeMaterialBatchFormData(auth()->user(), $data);
    }
}
