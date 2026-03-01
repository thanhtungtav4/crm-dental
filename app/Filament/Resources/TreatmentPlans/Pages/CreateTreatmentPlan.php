<?php

namespace App\Filament\Resources\TreatmentPlans\Pages;

use App\Filament\Resources\TreatmentPlans\TreatmentPlanResource;
use App\Support\BranchAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateTreatmentPlan extends CreateRecord
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (is_numeric($data['branch_id'] ?? null)) {
            BranchAccess::assertCanAccessBranch(
                branchId: (int) $data['branch_id'],
                field: 'branch_id',
                message: 'Bạn không thể tạo kế hoạch điều trị ở chi nhánh ngoài phạm vi được phân quyền.',
            );
        }

        return $data;
    }
}
