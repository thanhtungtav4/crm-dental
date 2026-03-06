<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (is_numeric($data['first_branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['first_branch_id'],
                field: 'first_branch_id',
                message: 'Bạn không thể tạo bệnh nhân ở chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        if (! empty($data['email'])) {
            $emailHash = Patient::emailSearchHash((string) $data['email']);
            $exists = $emailHash !== null
                ? Patient::withTrashed()->where('email_search_hash', $emailHash)->exists()
                : false;

            if ($exists) {
                throw ValidationException::withMessages([
                    'email' => 'Email bệnh nhân đã tồn tại.',
                ]);
            }
        }
        if (! empty($data['phone'])) {
            $phoneHash = Patient::phoneSearchHash((string) $data['phone']);
            $exists = $phoneHash !== null
                ? Patient::withTrashed()
                    ->where('phone_search_hash', $phoneHash)
                    ->when(
                        ! empty($data['first_branch_id']),
                        fn ($query) => $query->where('first_branch_id', $data['first_branch_id']),
                        fn ($query) => $query->whereNull('first_branch_id')
                    )
                    ->exists()
                : false;
            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'Số điện thoại bệnh nhân đã tồn tại trong chi nhánh đã chọn.',
                ]);
            }
        }

        return $data;
    }
}
