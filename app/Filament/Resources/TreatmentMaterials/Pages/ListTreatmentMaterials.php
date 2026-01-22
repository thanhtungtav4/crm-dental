<?php

namespace App\Filament\Resources\TreatmentMaterials\Pages;

use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTreatmentMaterials extends ListRecords
{
    protected static string $resource = TreatmentMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
