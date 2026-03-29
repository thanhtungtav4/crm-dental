<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PatientExamDoctorReadModelService
{
    public function __construct(protected PatientAssignmentAuthorizer $patientAssignmentAuthorizer) {}

    /**
     * @return Collection<int, User>
     */
    public function options(?User $actor, ?int $branchId, string $search = '', int $limit = 10): Collection
    {
        return $this->patientAssignmentAuthorizer
            ->scopeAssignableDoctors(User::query(), $actor, $branchId)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);
    }

    public function find(?User $actor, ?int $branchId, int $doctorId): ?User
    {
        return $this->patientAssignmentAuthorizer
            ->scopeAssignableDoctors(User::query(), $actor, $branchId)
            ->whereKey($doctorId)
            ->first(['id', 'name']);
    }

    public function name(?int $doctorId): string
    {
        if (! is_numeric($doctorId)) {
            return '';
        }

        return (string) (User::query()->whereKey((int) $doctorId)->value('name') ?? '');
    }
}
