<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\CareTicketService;
use App\Services\PatientConversionService;

class AppointmentObserver
{
    protected PatientConversionService $conversionService;
    protected CareTicketService $careTicketService;

    public function __construct(PatientConversionService $conversionService, CareTicketService $careTicketService)
    {
        $this->conversionService = $conversionService;
        $this->careTicketService = $careTicketService;
    }

    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        $this->careTicketService->syncAppointment($appointment);
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        // Tự động chuyển Customer thành Patient khi appointment hoàn thành
        if ($appointment->wasChanged('status') && $appointment->status === Appointment::STATUS_COMPLETED) {
            $this->handleConversion($appointment);
        }

        if ($appointment->wasChanged(['status', 'date', 'assigned_to', 'doctor_id', 'patient_id', 'note'])) {
            $this->careTicketService->syncAppointment($appointment);
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
        $this->careTicketService->cancelBySource(Appointment::class, $appointment->id, 'appointment_reminder');
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
