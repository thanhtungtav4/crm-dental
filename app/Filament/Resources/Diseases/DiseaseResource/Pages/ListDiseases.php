<?php

namespace App\Filament\Resources\Diseases\DiseaseResource\Pages;

use App\Filament\Resources\Diseases\DiseaseResource;
use Filament\Resources\Pages\ListRecords;

class ListDiseases extends ListRecords
{
    protected static string $resource = DiseaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
