<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Services\CareTicketService;
use App\Services\PatientConversionService;
use App\Services\VisitEpisodeService;

class AppointmentObserver
{
    protected PatientConversionService $conversionService;
    protected CareTicketService $careTicketService;
    protected VisitEpisodeService $visitEpisodeService;

    public function __construct(
        PatientConversionService $conversionService,
        CareTicketService $careTicketService,
        VisitEpisodeService $visitEpisodeService
    )
    {
        $this->conversionService = $conversionService;
        $this->careTicketService = $careTicketService;
        $this->visitEpisodeService = $visitEpisodeService;
    }

    /**
     * Handle the Appointment "created" event.
     */
    public function created(Appointment $appointment): void
    {
        $this->careTicketService->syncAppointment($appointment);
        $this->visitEpisodeService->syncFromAppointment($appointment, true);
    }

    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        $statusChanged = $appointment->wasChanged('status');

        // Tự động chuyển Customer thành Patient khi appointment hoàn thành
        if ($statusChanged && $appointment->status === Appointment::STATUS_COMPLETED) {
            $this->handleConversion($appointment);
        }

        if ($appointment->wasChanged(['status', 'date', 'duration_minutes', 'assigned_to', 'doctor_id', 'patient_id', 'branch_id', 'note'])) {
            $this->careTicketService->syncAppointment($appointment);
            $this->visitEpisodeService->syncFromAppointment($appointment, $statusChanged);
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
        $this->visitEpisodeService->markAppointmentDeleted($appointment);
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
