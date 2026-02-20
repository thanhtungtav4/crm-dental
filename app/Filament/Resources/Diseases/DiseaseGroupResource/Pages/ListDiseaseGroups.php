<?php

namespace App\Filament\Resources\Diseases\DiseaseGroupResource\Pages;

use App\Filament\Resources\Diseases\DiseaseGroupResource;
use Filament\Resources\Pages\ListRecords;

class ListDiseaseGroups extends ListRecords
{
    protected static string $resource = DiseaseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
