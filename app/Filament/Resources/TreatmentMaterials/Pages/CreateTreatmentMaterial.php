<?php

namespace App\Filament\Resources\TreatmentMaterials\Pages;

use App\Filament\Resources\TreatmentMaterials\TreatmentMaterialResource;
use App\Services\TreatmentMaterialUsageService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTreatmentMaterial extends CreateRecord
{
    protected static string $resource = TreatmentMaterialResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(TreatmentMaterialUsageService::class)->create($data);
    }
}
