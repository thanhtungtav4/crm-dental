<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class TreatmentAssignmentAuthorizer
{
    public function __construct(protected PatientAssignmentAuthorizer $patientAssignmentAuthorizer) {}

    /**
     * @return array<int, string>
     */
    public function assignableDoctorOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->patientAssignmentAuthorizer->assignableDoctorOptions($actor, $branchId);
    }

    /**
     * @return array<int, string>
     */
    public function assignableStaffOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->patientAssignmentAuthorizer->assignableStaffOptions($actor, $branchId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeTreatmentPlanFormData(?User $actor, array $data): array
    {
        $data['doctor_id'] = $this->sanitizeAssignableDoctorId(
            actor: $actor,
            doctorId: $this->normalizeNullableInt($data['doctor_id'] ?? null),
            branchId: $this->normalizeNullableInt($data['branch_id'] ?? null),
            field: 'doctor_id',
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeTreatmentSessionFormData(?User $actor, array $data, ?int $branchId): array
    {
        $data['doctor_id'] = $this->sanitizeAssignableDoctorId(
            actor: $actor,
            doctorId: $this->normalizeNullableInt($data['doctor_id'] ?? null),
            branchId: $branchId,
            field: 'doctor_id',
        );
        $data['assistant_id'] = $this->sanitizeAssignableStaffId(
            actor: $actor,
            staffId: $this->normalizeNullableInt($data['assistant_id'] ?? null),
            branchId: $branchId,
            field: 'assistant_id',
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeTreatmentMaterialUsageData(?User $actor, array $data, ?int $branchId): array
    {
        $resolvedActorId = $actor instanceof User
            ? $actor->getKey()
            : $this->normalizeNullableInt($data['used_by'] ?? null);

        $data['used_by'] = $this->sanitizeAssignableStaffId(
            actor: $actor,
            staffId: is_numeric($resolvedActorId) ? (int) $resolvedActorId : null,
            branchId: $branchId,
            field: 'used_by',
        );

        return $data;
    }

    public function isAssignableDoctorId(?User $actor, ?int $doctorId, ?int $branchId): bool
    {
        if ($doctorId === null) {
            return true;
        }

        return $this->patientAssignmentAuthorizer
            ->scopeAssignableDoctors(User::query()->whereKey($doctorId), $actor, $branchId)
            ->exists();
    }

    public function isAssignableStaffId(?User $actor, ?int $staffId, ?int $branchId): bool
    {
        if ($staffId === null) {
            return true;
        }

        return $this->patientAssignmentAuthorizer
            ->scopeAssignableStaff(User::query()->whereKey($staffId), $actor, $branchId)
            ->exists();
    }

    protected function sanitizeAssignableDoctorId(?User $actor, ?int $doctorId, ?int $branchId, string $field): ?int
    {
        if ($doctorId === null || $this->isAssignableDoctorId($actor, $doctorId, $branchId)) {
            return $doctorId;
        }

        throw ValidationException::withMessages([
            $field => 'Bác sĩ được chọn không thuộc phạm vi chi nhánh được phép gán.',
        ]);
    }

    protected function sanitizeAssignableStaffId(?User $actor, ?int $staffId, ?int $branchId, string $field): ?int
    {
        if ($staffId === null || $this->isAssignableStaffId($actor, $staffId, $branchId)) {
            return $staffId;
        }

        throw ValidationException::withMessages([
            $field => 'Nhân sự được chọn không thuộc phạm vi chi nhánh được phép gán.',
        ]);
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
