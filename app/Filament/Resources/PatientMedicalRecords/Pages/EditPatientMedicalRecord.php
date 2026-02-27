<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPatientMedicalRecord extends EditRecord
{
    protected static string $resource = PatientMedicalRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
