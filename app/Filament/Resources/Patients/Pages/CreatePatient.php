<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Services\PatientAssignmentAuthorizer;
use App\Services\PatientOnboardingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(PatientAssignmentAuthorizer::class)
            ->sanitizePatientFormData(auth()->user(), $data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(PatientOnboardingService::class)->create($data);
    }
}
