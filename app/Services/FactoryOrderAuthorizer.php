<?php

namespace App\Services;

use App\Models\FactoryOrder;
use App\Models\Patient;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FactoryOrderAuthorizer
{
    public function __construct(
        protected PatientAssignmentAuthorizer $patientAssignmentAuthorizer,
    ) {}

    /**
     * @return array<int, string>
     */
    public function assignableDoctorOptions(?User $actor, ?int $branchId = null): array
    {
        return $this->patientAssignmentAuthorizer->assignableDoctorOptions($actor, $branchId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeFactoryOrderData(?User $actor, array $data, ?FactoryOrder $record = null): array
    {
        $patientId = isset($data['patient_id']) && filled($data['patient_id'])
            ? (int) $data['patient_id']
            : null;

        if ($patientId === null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Vui lòng chọn bệnh nhân cho lệnh labo.',
            ]);
        }

        $patient = Patient::query()
            ->when(
                ! $actor?->hasRole('Admin'),
                fn (Builder $query): Builder => BranchAccess::scopeQueryByAccessibleBranches($query, 'first_branch_id'),
            )
            ->find($patientId);

        if (! $patient instanceof Patient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Bệnh nhân được chọn không thuộc phạm vi chi nhánh được phép thao tác.',
            ]);
        }

        $patientBranchId = $patient->first_branch_id !== null ? (int) $patient->first_branch_id : null;

        if ($patientBranchId === null) {
            throw ValidationException::withMessages([
                'patient_id' => 'Bệnh nhân chưa có chi nhánh gốc để tạo lệnh labo.',
            ]);
        }

        $branchId = isset($data['branch_id']) && filled($data['branch_id'])
            ? (int) $data['branch_id']
            : $patientBranchId;

        BranchAccess::assertCanAccessBranch(
            branchId: $branchId,
            field: 'branch_id',
            message: 'Bạn không có quyền thao tác lệnh labo ở chi nhánh này.',
        );

        if ($branchId !== $patientBranchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Chi nhánh của lệnh labo phải trùng với chi nhánh gốc của bệnh nhân.',
            ]);
        }

        $data['patient_id'] = $patient->id;
        $data['branch_id'] = $branchId;

        $data['doctor_id'] = $this->patientAssignmentAuthorizer->assertAssignableDoctorId(
            actor: $actor,
            doctorId: isset($data['doctor_id']) && filled($data['doctor_id']) ? (int) $data['doctor_id'] : null,
            branchId: $branchId,
            field: 'doctor_id',
        );

        return $data;
    }
}
