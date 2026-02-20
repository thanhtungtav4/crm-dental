<?php

namespace App\Filament\Resources\Diseases\DiseaseResource\Pages;

use App\Filament\Resources\Diseases\DiseaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDisease extends EditRecord
{
    protected static string $resource = DiseaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
