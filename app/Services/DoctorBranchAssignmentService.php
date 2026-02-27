<?php

namespace App\Services;

use App\Models\DoctorBranchAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class DoctorBranchAssignmentService
{
    /**
     * @return array<int, string>
     */
    public function doctorOptionsForBranch(?int $branchId, ?CarbonInterface $at = null): array
    {
        if ($branchId === null) {
            return User::role('Doctor')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        $doctorIds = $this->doctorIdsForBranch($branchId, $at);

        if ($doctorIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $doctorIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function ensureDoctorCanWorkAtBranch(int $doctorId, int $branchId, ?CarbonInterface $at = null): void
    {
        if (! $this->isDoctorAssignedToBranch($doctorId, $branchId, $at)) {
            throw ValidationException::withMessages([
                'doctor_id' => 'Bác sĩ chưa được phân công tại chi nhánh này trong khung thời gian đã chọn.',
            ]);
        }
    }

    public function isDoctorAssignedToBranch(int $doctorId, int $branchId, ?CarbonInterface $at = null): bool
    {
        $doctor = User::query()
            ->whereKey($doctorId)
            ->first();

        if (! $doctor || ! $doctor->hasRole('Doctor')) {
            return false;
        }

        $assignments = DoctorBranchAssignment::query()
            ->forUser($doctorId)
            ->get(['id', 'branch_id', 'is_active', 'assigned_from', 'assigned_until']);

        if ($assignments->isEmpty()) {
            return (int) ($doctor->branch_id ?? 0) === $branchId;
        }

        $referenceDate = ($at ?? now())->toDateString();

        return $assignments
            ->where('branch_id', $branchId)
            ->contains(function (DoctorBranchAssignment $assignment) use ($referenceDate): bool {
                if (! $assignment->is_active) {
                    return false;
                }

                if ($assignment->assigned_from?->toDateString() && $assignment->assigned_from->toDateString() > $referenceDate) {
                    return false;
                }

                if ($assignment->assigned_until?->toDateString() && $assignment->assigned_until->toDateString() < $referenceDate) {
                    return false;
                }

                return true;
            });
    }

    /**
     * @return array<int, int>
     */
    public function doctorIdsForBranch(int $branchId, ?CarbonInterface $at = null): array
    {
        $assignedDoctorIds = DoctorBranchAssignment::query()
            ->forBranch($branchId)
            ->activeAt($at)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $legacyDoctorIds = User::role('Doctor')
            ->where('branch_id', $branchId)
            ->whereNotIn('id', DoctorBranchAssignment::query()->select('user_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        return $assignedDoctorIds
            ->merge($legacyDoctorIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>  $branchIds
     */
    public function syncDoctorAssignments(User $doctor, array $branchIds, ?int $actorId = null): void
    {
        $normalizedBranchIds = collect($branchIds)
            ->filter(fn ($branchId): bool => filled($branchId))
            ->map(fn ($branchId): int => (int) $branchId)
            ->unique()
            ->values();

        if ($normalizedBranchIds->isEmpty() && $doctor->branch_id) {
            $normalizedBranchIds = collect([(int) $doctor->branch_id]);
        }

        if (! $doctor->hasRole('Doctor')) {
            DoctorBranchAssignment::query()
                ->where('user_id', $doctor->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'is_primary' => false,
                    'assigned_until' => today()->toDateString(),
                    'note' => 'Auto-deactivated because user no longer has Doctor role.',
                ]);

            return;
        }

        $primaryBranchId = (int) ($doctor->branch_id ?: ($normalizedBranchIds->first() ?? 0));

        foreach ($normalizedBranchIds as $branchId) {
            $assignment = DoctorBranchAssignment::query()->firstOrNew([
                'user_id' => $doctor->id,
                'branch_id' => $branchId,
            ]);

            $assignment->is_active = true;
            $assignment->is_primary = $branchId === $primaryBranchId;
            $assignment->assigned_from = $assignment->assigned_from ?? today()->toDateString();
            $assignment->assigned_until = null;
            $assignment->created_by = $assignment->created_by ?? $actorId;
            $assignment->save();
        }

        DoctorBranchAssignment::query()
            ->where('user_id', $doctor->id)
            ->when(
                $normalizedBranchIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('branch_id', $normalizedBranchIds->all())
            )
            ->update([
                'is_active' => false,
                'is_primary' => false,
                'assigned_until' => today()->toDateString(),
            ]);

        if (! $doctor->branch_id && $normalizedBranchIds->isNotEmpty()) {
            $doctor->forceFill([
                'branch_id' => $normalizedBranchIds->first(),
            ])->save();
        }
    }
}
