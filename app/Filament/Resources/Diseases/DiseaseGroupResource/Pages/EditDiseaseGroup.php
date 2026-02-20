<?php

namespace App\Filament\Resources\Diseases\DiseaseGroupResource\Pages;

use App\Filament\Resources\Diseases\DiseaseGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDiseaseGroup extends EditRecord
{
    protected static string $resource = DiseaseGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
