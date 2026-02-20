<?php

namespace App\Filament\Resources\Diseases\DiseaseResource\Pages;

use App\Filament\Resources\Diseases\DiseaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDisease extends CreateRecord
{
    protected static string $resource = DiseaseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
