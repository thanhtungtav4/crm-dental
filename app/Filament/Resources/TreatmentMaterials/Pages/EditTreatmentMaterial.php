<?php

namespace App\Filament\Resources\TreatmentMaterials\Pages;

use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreatmentMaterial extends EditRecord
{
    protected static string $resource = TreatmentMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
