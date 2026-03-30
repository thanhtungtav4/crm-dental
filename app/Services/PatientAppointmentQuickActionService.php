<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Support\BranchAccess;

class PatientAppointmentQuickActionService
{
    public function __construct(
        private readonly AppointmentSchedulingService $appointmentSchedulingService,
        private readonly PatientConversionService $patientConversionService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForPatient(Patient $patient, array $data): Appointment
    {
        $resolvedBranchId = is_numeric($data['branch_id'] ?? null)
            ? (int) $data['branch_id']
            : (is_numeric($patient->first_branch_id) ? (int) $patient->first_branch_id : null);

        BranchAccess::assertCanAccessBranch(
            branchId: $resolvedBranchId,
            field: 'branch_id',
            message: 'Bạn không thể tạo lịch hẹn ở chi nhánh ngoài phạm vi được phân quyền.',
        );

        return $this->appointmentSchedulingService->create($this->appointmentPayload(
            patientId: $patient->id,
            customerId: null,
            branchId: $resolvedBranchId,
            data: $data,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForCustomer(Customer $customer, array $data): Appointment
    {
        $resolvedBranchId = is_numeric($data['branch_id'] ?? null)
            ? (int) $data['branch_id']
            : (is_numeric($customer->branch_id) ? (int) $customer->branch_id : null);

        BranchAccess::assertCanAccessBranch(
            branchId: $resolvedBranchId,
            field: 'branch_id',
            message: 'Bạn không thể tạo lịch hẹn ở chi nhánh ngoài phạm vi được phân quyền.',
        );

        $patient = $this->patientConversionService->convert($customer);

        return $this->appointmentSchedulingService->create($this->appointmentPayload(
            patientId: $patient?->id,
            customerId: $customer->id,
            branchId: $resolvedBranchId,
            data: $data,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function appointmentPayload(?int $patientId, ?int $customerId, ?int $branchId, array $data): array
    {
        return [
            'patient_id' => $patientId,
            'customer_id' => $customerId,
            'doctor_id' => $data['doctor_id'] ?? null,
            'branch_id' => $branchId,
            'date' => $data['date'],
            'appointment_kind' => $data['appointment_kind'] ?? 'booking',
            'status' => $data['status'] ?? Appointment::STATUS_SCHEDULED,
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'reschedule_reason' => $data['reschedule_reason'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
