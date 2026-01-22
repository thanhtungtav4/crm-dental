<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPatientMedicalRecord extends EditRecord
{
    protected static string $resource = PatientMedicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
