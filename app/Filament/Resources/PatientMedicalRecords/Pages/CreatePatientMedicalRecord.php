<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePatientMedicalRecord extends CreateRecord
{
    protected static string $resource = PatientMedicalRecordResource::class;

    public ?int $patientId = null;

    public function mount(): void
    {
        $this->patientId = request()->integer('patient_id') ?: null;
        $patientId = $this->patientId;

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
                    PatientMedicalRecordResource::getUrl('edit', [
                        'record' => $patient->medicalRecord,
                        'patient_id' => $patient->id,
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToPatientProfile')
                ->label('Về hồ sơ bệnh nhân')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn (): ?string => $this->resolvePatientProfileUrl())
                ->visible(fn (): bool => $this->resolvePatientProfileUrl() !== null),
        ];
    }

    protected function resolvePatientProfileUrl(): ?string
    {
        if (! is_int($this->patientId) || $this->patientId <= 0) {
            return null;
        }

        $patient = Patient::query()->find($this->patientId);
        $authUser = auth()->user();

        if (! $patient || ! $authUser || $authUser->cannot('view', $patient)) {
            return null;
        }

        return PatientResource::getUrl('view', [
            'record' => $patient,
            'tab' => 'exam-treatment',
        ]);
    }
}
