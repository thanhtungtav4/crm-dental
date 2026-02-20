<?php

namespace App\Filament\Resources\Diseases\DiseaseGroupResource\Pages;

use App\Filament\Resources\Diseases\DiseaseGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiseaseGroup extends CreateRecord
{
    protected static string $resource = DiseaseGroupResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
