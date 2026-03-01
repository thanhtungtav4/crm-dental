<?php

namespace App\Filament\Resources\PatientMedicalRecords\Pages;

use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use Filament\Actions\Action;
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
            Action::make('backToPatientProfile')
                ->label('Về hồ sơ bệnh nhân')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn (): ?string => $this->resolvePatientProfileUrl())
                ->visible(fn (): bool => $this->resolvePatientProfileUrl() !== null),
            DeleteAction::make(),
        ];
    }

    protected function resolvePatientProfileUrl(): ?string
    {
        $patient = $this->getRecord()->patient;
        $authUser = auth()->user();

        if (! $patient instanceof Patient || ! $authUser || $authUser->cannot('view', $patient)) {
            return null;
        }

        return PatientResource::getUrl('view', [
            'record' => $patient,
            'tab' => 'exam-treatment',
        ]);
    }
}
