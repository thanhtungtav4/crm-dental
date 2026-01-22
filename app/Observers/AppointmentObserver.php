<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\PatientConversionService;

class AppointmentObserver
{
    protected PatientConversionService $conversionService;

    public function __construct(PatientConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        //
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        // Tự động chuyển Customer thành Patient khi appointment hoàn thành
        if ($appointment->wasChanged('status') && $appointment->status === 'done') {
            $this->handleConversion($appointment);
        }
    }

    /**
     * Convert Customer to Patient via Service
     */
    protected function handleConversion(Appointment $appointment): void
    {
        // Nếu đã có patient_id rồi thì skip
        if ($appointment->patient_id) {
            return;
        }

        // Nếu không có customer_id thì skip
        if (!$appointment->customer_id) {
            return;
        }

        $customer = $appointment->customer;
        if (!$customer) {
            return;
        }

        // Delegate to Service
        $this->conversionService->convert($customer, $appointment);
    }

    /**
     * Handle the Appointment "deleted" event.
     */
    public function deleted(Appointment $appointment): void
    {
        //
    }

    /**
     * Handle the Appointment "restored" event.
     */
    public function restored(Appointment $appointment): void
    {
        //
    }

    /**
     * Handle the Appointment "force deleted" event.
     */
    public function forceDeleted(Appointment $appointment): void
    {
        //
    }
}
