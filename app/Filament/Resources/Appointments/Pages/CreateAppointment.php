<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Services\AppointmentSchedulingService;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (is_numeric($data['branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['branch_id'],
                field: 'branch_id',
                message: 'Bạn không thể tạo lịch hẹn ở chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(AppointmentSchedulingService::class)->create($data);
    }
}
