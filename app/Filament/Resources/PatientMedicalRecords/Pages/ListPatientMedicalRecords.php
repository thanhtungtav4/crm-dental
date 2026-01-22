<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPatientMedicalRecords extends ListRecords
{
    protected static string $resource = PatientMedicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
