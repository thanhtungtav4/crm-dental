<?php

namespace App\Filament\Resources\MaterialBatches\Pages;

use App\Filament\Resources\MaterialBatches\MaterialBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialBatches extends ListRecords
{
    protected static string $resource = MaterialBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
