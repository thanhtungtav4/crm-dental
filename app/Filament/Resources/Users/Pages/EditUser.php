<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\DoctorBranchAssignmentService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @var array<int, int>
     */
    protected array $doctorBranchIds = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->doctorBranchIds = collect($data['doctor_branch_ids'] ?? [])
            ->filter(fn ($branchId): bool => filled($branchId))
            ->map(fn ($branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();

        unset($data['doctor_branch_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(DoctorBranchAssignmentService::class)->syncDoctorAssignments(
            doctor: $this->record,
            branchIds: $this->doctorBranchIds,
            actorId: auth()->id(),
        );
    }
}
