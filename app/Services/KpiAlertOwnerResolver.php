<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class KpiAlertOwnerResolver
{
    public function resolve(?int $branchId): ?int
    {
        if ($branchId !== null) {
            $branchManagerId = User::query()
                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'Manager'))
                ->where(function (Builder $query) use ($branchId): void {
                    $query->where('branch_id', $branchId)
                        ->orWhereHas('activeDoctorBranchAssignments', fn (Builder $assignmentQuery) => $assignmentQuery->where('branch_id', $branchId));
                })
                ->orderByRaw('case when branch_id = ? then 0 else 1 end', [$branchId])
                ->orderBy('id')
                ->value('id');

            if ($branchManagerId) {
                return (int) $branchManagerId;
            }
        }

        $adminId = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'Admin'))
            ->orderBy('id')
            ->value('id');

        return $adminId ? (int) $adminId : null;
    }
}
