<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PatientConversionService
{
    /**
     * Convert a Customer (Lead) to a Patient.
     * 
     * @param Customer $customer
     * @param Appointment|null $appointment Optional appointment context
     * @return Patient|null Returns the patient instance or null on failure
     */
    public function convert(Customer $customer, ?Appointment $appointment = null): ?Patient
    {
        $targetBranchId = $appointment?->branch_id
            ?? $customer->branch_id
            ?? auth()->user()?->branch_id;

        // 1. Basic validation
        if ($customer->patient) {
            // Already has a patient record linked
            return $this->handleExistingPatient($customer, $customer->patient, $appointment, $targetBranchId);
        }

        // 2. Check if a patient already exists by customer identity first
        $existingPatient = Patient::where('customer_id', $customer->id)->first();

        // 3. Dedupe by phone + clinic before creating new patient
        if (! $existingPatient && ! empty($customer->phone)) {
            $existingPatient = $this->findByPhoneAndClinic($customer->phone, $targetBranchId);
        }

        if ($existingPatient) {
            return $this->handleExistingPatient($customer, $existingPatient, $appointment, $targetBranchId);
        }

        return DB::transaction(function () use ($customer, $appointment) {
            try {
                // 4. Create Patient
                $patient = Patient::create([
                    'customer_id' => $customer->id,
                    'first_branch_id' => $appointment?->branch_id ?? $customer->branch_id ?? auth()->user()?->branch_id,
                    'full_name' => $customer->full_name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'gender' => $customer->gender ?? 'other', // Assuming gender might be on customer or default
                    'birthday' => $customer->birthday ?? null,
                    'address' => $customer->address ?? null,
                    'customer_group_id' => $customer->customer_group_id,
                    'promotion_group_id' => $customer->promotion_group_id,
                    'owner_staff_id' => $customer->assigned_to,
                    'created_by' => auth()->id() ?? $customer->created_by,
                    'updated_by' => auth()->id() ?? $customer->updated_by,
                ]);

                // 5. Update Customer status
                $customer->status = 'converted';
                $customer->save();

                // 6. Link Appointment if exists
                if ($appointment) {
                    $appointment->patient_id = $patient->id;
                    $appointment->customer_id = $appointment->customer_id ?: $customer->id;
                    $appointment->saveQuietly();
                }

                // 7. Notify
                $this->sendSuccessNotification($customer, $patient);

                return $patient;

            } catch (\Exception $e) {
                Log::error("Failed to convert customer {$customer->id} to patient: " . $e->getMessage());

                Notification::make()
                    ->title('Lỗi chuyển đổi dữ liệu')
                    ->body('Không thể tạo hồ sơ bệnh nhân. Vui lòng thử lại.')
                    ->danger()
                    ->send();

                throw $e; // Re-throw to rollback transaction
            }
        });
    }

    /**
     * Handle case where Patient already exists.
     */
    protected function handleExistingPatient(
        Customer $customer,
        Patient $patient,
        ?Appointment $appointment,
        ?int $targetBranchId = null,
    ): Patient
    {
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

        if ($dirtyPatient) {
            $patient->save();
        }

        // Link appointment if needed
        if ($appointment && !$appointment->patient_id) {
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

    protected function findByPhoneAndClinic(string $phone, ?int $branchId): ?Patient
    {
        $exactMatch = Patient::query()
            ->when($branchId, fn ($query) => $query->where('first_branch_id', $branchId))
            ->where('phone', $phone)
            ->first();

        if ($exactMatch) {
            return $exactMatch;
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === null) {
            return null;
        }

        $candidates = Patient::query()
            ->when($branchId, fn ($query) => $query->where('first_branch_id', $branchId))
            ->whereNotNull('phone')
            ->get();

        return $candidates->first(function (Patient $patient) use ($normalizedPhone): bool {
            return $this->normalizePhone($patient->phone) === $normalizedPhone;
        });
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $phone);

        if (! $normalized) {
            return null;
        }

        return Str::startsWith($normalized, '84')
            ? '0' . substr($normalized, 2)
            : $normalized;
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
}
