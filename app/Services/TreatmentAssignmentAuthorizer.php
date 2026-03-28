<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
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
        $data['patient_id'] = $this->assertAccessiblePatientId(
            actor: $actor,
            patientId: $this->normalizeNullableInt($data['patient_id'] ?? null),
            field: 'patient_id',
        );

        $data['doctor_id'] = $this->sanitizeAssignableDoctorId(
            actor: $actor,
            doctorId: $this->normalizeNullableInt($data['doctor_id'] ?? null),
            branchId: $this->normalizeNullableInt($data['branch_id'] ?? null),
            field: 'doctor_id',
        );

        return $data;
    }

    public function scopeAccessiblePatients(Builder $query, ?User $actor): Builder
    {
        return $this->scopeQueryByActorBranches($query, $actor, 'first_branch_id');
    }

    public function findAccessiblePatient(?User $actor, ?int $patientId): ?Patient
    {
        if ($patientId === null) {
            return null;
        }

        return $this->scopeAccessiblePatients(Patient::query(), $actor)->find($patientId);
    }

    public function assertAccessiblePatientId(?User $actor, ?int $patientId, string $field): int
    {
        if ($patientId === null) {
            throw ValidationException::withMessages([
                $field => 'Vui lòng chọn bệnh nhân cho kế hoạch điều trị.',
            ]);
        }

        $patient = $this->findAccessiblePatient($actor, $patientId);

        if (! $patient instanceof Patient) {
            throw ValidationException::withMessages([
                $field => 'Bệnh nhân được chọn không thuộc phạm vi chi nhánh được phép thao tác.',
            ]);
        }

        return (int) $patient->getKey();
    }

    public function scopeAccessibleTreatmentPlans(Builder $query, ?User $actor): Builder
    {
        return $this->scopeQueryByActorBranches($query, $actor, 'branch_id');
    }

    public function findAccessibleTreatmentPlan(?User $actor, ?int $treatmentPlanId): ?TreatmentPlan
    {
        if ($treatmentPlanId === null) {
            return null;
        }

        return $this->scopeAccessibleTreatmentPlans(TreatmentPlan::query(), $actor)->find($treatmentPlanId);
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

    protected function scopeQueryByActorBranches(Builder $query, ?User $actor, string $column): Builder
    {
        return BranchAccess::scopeQueryByUserAccessibleBranches($query, $actor, $column);
    }

    protected function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
