<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Services\PatientAssignmentAuthorizer;
use Filament\Resources\Pages\CreateRecord;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PatientAssignmentAuthorizer::class)
            ->sanitizePatientFormData(auth()->user(), $data);
    }
}
