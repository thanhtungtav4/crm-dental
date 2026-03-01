<?php

namespace App\Filament\Resources\MaterialIssueNotes\Pages;

use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialIssueNote extends CreateRecord
{
    protected static string $resource = MaterialIssueNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $patientId = request()->integer('patient_id');
        if ($patientId && blank($data['patient_id'] ?? null)) {
            $data['patient_id'] = $patientId;
        }

        return $data;
    }
}
