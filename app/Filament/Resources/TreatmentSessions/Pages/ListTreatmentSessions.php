<?php

namespace App\Filament\Resources\TreatmentSessions\Pages;

use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTreatmentSessions extends ListRecords
{
    protected static string $resource = TreatmentSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
