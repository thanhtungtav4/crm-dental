<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Patient;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // 1. Basic validation
        if ($customer->patient) {
            // Already has a patient record linked
            return $customer->patient;
        }

        // 2. Check if a patient exists with this phone number (to avoid duplicates)
        $existingPatient = Patient::where('customer_id', $customer->id)->first();

        if ($existingPatient) {
            // Logic integrity check: Customer has no relation loaded, but DB says yes.
            // We should link them if not linked? - In this schema, Patient belongs to Customer via customer_id.
            // So if existingPatient found, we just return it.
            return $this->handleExistingPatient($customer, $existingPatient, $appointment);
        }

        // Double check by phone if we want to be strict, but for now stick to ID mapping
        // implementation_plan calls for creating new if not exists.

        return DB::transaction(function () use ($customer, $appointment) {
            try {
                // 3. Create Patient
                $patient = Patient::create([
                    'customer_id' => $customer->id,
                    'patient_code' => $this->generatePatientCode(),
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

                // 4. Update Customer status
                $customer->status = 'converted';
                $customer->save();

                // 5. Link Appointment if exists
                if ($appointment) {
                    $appointment->patient_id = $patient->id;
                    $appointment->saveQuietly();
                }

                // 6. Notify
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
    protected function handleExistingPatient(Customer $customer, Patient $patient, ?Appointment $appointment): Patient
    {
        // Link appointment if needed
        if ($appointment && !$appointment->patient_id) {
            $appointment->patient_id = $patient->id;
            $appointment->saveQuietly();
        }

        // Ensure customer status is correct
        if ($customer->status !== 'converted') {
            $customer->status = 'converted';
            $customer->save();
        }

        return $patient;
    }

    protected function generatePatientCode(): string
    {
        // Logic from Observer: BN000001
        // Using locking or just atomic increment would be better, but sticking to existing logic pattern for now
        $maxId = Patient::max('id') ?? 0;
        return 'BN' . str_pad($maxId + 1, 6, '0', STR_PAD_LEFT);
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
}
