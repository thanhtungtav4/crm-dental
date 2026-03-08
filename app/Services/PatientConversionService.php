<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use App\Support\PatientIdentityNormalizer;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientConversionService
{
    /**
     * Convert a Customer (Lead) to a Patient.
     *
     * @param  Appointment|null  $appointment  Optional appointment context
     * @return Patient|null Returns the patient instance or null on failure
     */
    public function convert(Customer $customer, ?Appointment $appointment = null): ?Patient
    {
        $targetBranchId = $this->resolveTargetBranchId($customer, $appointment);

        try {
            return DB::transaction(function () use ($customer, $appointment, $targetBranchId): Patient {
                $lockedCustomer = Customer::query()
                    ->lockForUpdate()
                    ->findOrFail($customer->getKey());

                $lockedAppointment = $this->lockAppointment($appointment);
                $resolvedBranchId = $lockedAppointment?->branch_id ?? $targetBranchId;

                $existingPatient = $this->findExistingPatientForLockedCustomer($lockedCustomer, $resolvedBranchId);

                if ($existingPatient) {
                    return $this->handleExistingPatient($lockedCustomer, $existingPatient, $lockedAppointment, $resolvedBranchId);
                }

                $this->lockPeerCustomersForIdentity($lockedCustomer);

                $existingPatient = $this->findExistingPatientForLockedCustomer($lockedCustomer, $resolvedBranchId);

                if ($existingPatient) {
                    return $this->handleExistingPatient($lockedCustomer, $existingPatient, $lockedAppointment, $resolvedBranchId);
                }

                return $this->createPatientFromCustomer(
                    customer: $lockedCustomer,
                    appointment: $lockedAppointment,
                    targetBranchId: $resolvedBranchId,
                );
            }, attempts: 5);
        } catch (\Throwable $exception) {
            Log::error("Failed to convert customer {$customer->id} to patient: ".$exception->getMessage());

            Notification::make()
                ->title('Lỗi chuyển đổi dữ liệu')
                ->body('Không thể tạo hồ sơ bệnh nhân. Vui lòng thử lại.')
                ->danger()
                ->send();

            throw $exception;
        }
    }

    /**
     * Handle case where Patient already exists.
     */
    protected function handleExistingPatient(
        Customer $customer,
        Patient $patient,
        ?Appointment $appointment,
        ?int $targetBranchId = null,
    ): Patient {
        $dirtyPatient = false;
        $linkedToCurrentCustomer = false;

        if (! $patient->customer_id) {
            $patient->customer_id = $customer->id;
            $dirtyPatient = true;
            $linkedToCurrentCustomer = true;
        } else {
            $linkedToCurrentCustomer = (int) $patient->customer_id === (int) $customer->id;
        }

        if (! $patient->first_branch_id && $targetBranchId) {
            $patient->first_branch_id = $targetBranchId;
            $dirtyPatient = true;
        }

        if (
            $targetBranchId
            && $patient->first_branch_id
            && (int) $patient->first_branch_id !== (int) $targetBranchId
        ) {
            $patient->branchTransferLogNote = 'Tự động đồng bộ chi nhánh khi tái sử dụng hồ sơ bệnh nhân hiện có từ luồng chuyển đổi khách hàng.';
            $patient->branchTransferActorId = is_numeric(auth()->id()) ? (int) auth()->id() : null;
            $patient->first_branch_id = $targetBranchId;
            $dirtyPatient = true;
        }

        if ($dirtyPatient) {
            $patient->save();
        }

        // Link appointment if needed
        if ($appointment && ! $appointment->patient_id) {
            $appointment->patient_id = $patient->id;
            $appointment->customer_id = $appointment->customer_id ?: $customer->id;
            $appointment->saveQuietly();
        }

        // Only mark converted when this customer is the canonical owner of patient profile.
        if ($linkedToCurrentCustomer && $customer->status !== 'converted') {
            $customer->status = 'converted';
            $customer->save();
        }

        if (! $linkedToCurrentCustomer) {
            $this->sendExistingPatientNotification($customer, $patient);
        }

        return $patient;
    }

    protected function findByPhoneAndClinic(string $phone, ?int $branchId, bool $lockForUpdate = false): ?Patient
    {
        $phoneHash = Patient::phoneSearchHash($phone);

        return $phoneHash !== null
            ? Patient::query()
                ->when($branchId, fn ($query) => $query->where('first_branch_id', $branchId))
                ->where('phone_search_hash', $phoneHash)
                ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
                ->orderBy('id')
                ->first()
            : null;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        return PatientIdentityNormalizer::normalizePhone($phone);
    }

    protected function sendSuccessNotification(Customer $customer, Patient $patient): void
    {
        $recipient = auth()->user();

        if ($recipient) {
            Notification::make()
                ->title('✅ Đã chuyển thành bệnh nhân!')
                ->body("Khách hàng \"{$customer->full_name}\" đã được chuyển thành bệnh nhân (Mã: {$patient->patient_code}).")
                ->success()
                ->sendToDatabase($recipient);
        }
    }

    protected function sendExistingPatientNotification(Customer $customer, Patient $patient): void
    {
        $recipient = auth()->user();

        if ($recipient) {
            Notification::make()
                ->title('Đã phát hiện hồ sơ bệnh nhân trùng')
                ->body("Khách hàng \"{$customer->full_name}\" trùng với hồ sơ {$patient->patient_code}. Hệ thống đã dùng hồ sơ hiện có.")
                ->warning()
                ->sendToDatabase($recipient);
        }
    }

    protected function resolveTargetBranchId(Customer $customer, ?Appointment $appointment): ?int
    {
        return $appointment?->branch_id
            ?? $customer->branch_id
            ?? auth()->user()?->branch_id;
    }

    protected function lockAppointment(?Appointment $appointment): ?Appointment
    {
        if (! $appointment?->exists) {
            return $appointment;
        }

        return Appointment::query()
            ->lockForUpdate()
            ->find($appointment->getKey());
    }

    protected function findExistingPatientForLockedCustomer(Customer $customer, ?int $targetBranchId): ?Patient
    {
        $existingByCustomer = Patient::query()
            ->where('customer_id', $customer->getKey())
            ->lockForUpdate()
            ->first();

        if ($existingByCustomer) {
            return $existingByCustomer;
        }

        if (! filled($customer->phone)) {
            return null;
        }

        $existingByBranch = $this->findByPhoneAndClinic((string) $customer->phone, $targetBranchId, true);

        if ($existingByBranch) {
            return $existingByBranch;
        }

        return $this->findByPhoneAndClinic((string) $customer->phone, null, true);
    }

    protected function lockPeerCustomersForIdentity(Customer $customer): void
    {
        if (! filled($customer->phone_search_hash)) {
            return;
        }

        Customer::query()
            ->where('phone_search_hash', (string) $customer->phone_search_hash)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    protected function createPatientFromCustomer(
        Customer $customer,
        ?Appointment $appointment,
        ?int $targetBranchId,
    ): Patient {
        try {
            $patient = Patient::query()->create([
                'customer_id' => $customer->id,
                'first_branch_id' => $targetBranchId,
                'full_name' => $customer->full_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'gender' => $customer->gender ?? 'other',
                'birthday' => $customer->birthday ?? null,
                'address' => $customer->address ?? null,
                'customer_group_id' => $customer->customer_group_id,
                'promotion_group_id' => $customer->promotion_group_id,
                'owner_staff_id' => $customer->assigned_to,
                'created_by' => auth()->id() ?? $customer->created_by,
                'updated_by' => auth()->id() ?? $customer->updated_by,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existingPatient = Patient::query()
                ->where('customer_id', $customer->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingPatient) {
                return $this->handleExistingPatient($customer, $existingPatient, $appointment, $targetBranchId);
            }

            throw $exception;
        }

        $customer->status = 'converted';
        $customer->save();

        if ($appointment) {
            $appointment->patient_id = $patient->id;
            $appointment->customer_id = $appointment->customer_id ?: $customer->id;
            $appointment->saveQuietly();
        }

        $this->sendSuccessNotification($customer, $patient);

        return $patient;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
