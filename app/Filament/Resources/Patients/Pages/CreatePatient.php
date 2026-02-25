<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['email'])) {
            $exists = Patient::withTrashed()->where('email', $data['email'])->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email bệnh nhân đã tồn tại.',
                ]);
            }
        }
        if (!empty($data['phone'])) {
            $exists = Patient::withTrashed()
                ->where('phone', $data['phone'])
                ->when(
                    ! empty($data['first_branch_id']),
                    fn ($query) => $query->where('first_branch_id', $data['first_branch_id']),
                    fn ($query) => $query->whereNull('first_branch_id')
                )
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Số điện thoại bệnh nhân đã tồn tại trong chi nhánh đã chọn.',
                ]);
            }
        }

        return $data;
    }
}
