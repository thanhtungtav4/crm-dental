<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Services\PatientAssignmentAuthorizer;
// No delete/restore actions for Patient
use Filament\Resources\Pages\EditRecord;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return app(PatientAssignmentAuthorizer::class)
            ->sanitizePatientFormData(auth()->user(), $data, $this->record);
    }
}
