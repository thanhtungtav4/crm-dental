<?php

namespace App\Filament\Resources\TreatmentSessions\Pages;

use App\Filament\Resources\TreatmentSessions\TreatmentSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTreatmentSession extends EditRecord
{
    protected static string $resource = TreatmentSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
