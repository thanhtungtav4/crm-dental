<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Models\Patient;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePatientMedicalRecord extends CreateRecord
{
    protected static string $resource = PatientMedicalRecordResource::class;

    public function mount(): void
    {
        $patientId = request()->integer('patient_id');

        if ($patientId) {
            $patient = Patient::query()
                ->with('medicalRecord:id,patient_id')
                ->find($patientId);

            if ($patient?->medicalRecord) {
                Notification::make()
                    ->title('Bệnh nhân đã có bệnh án điện tử, chuyển sang màn hình chỉnh sửa.')
                    ->info()
                    ->send();

                $this->redirect(
                    route('filament.admin.resources.patient-medical-records.edit', [
                        'record' => $patient->medicalRecord->id,
                    ])
                );

                return;
            }
        }

        parent::mount();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
