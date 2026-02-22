<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\CareTicketService;

class PrescriptionObserver
{
    public function __construct(protected CareTicketService $careTicketService)
    {
    }

    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        $this->careTicketService->syncPrescription($prescription);
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        if ($prescription->wasChanged(['treatment_date', 'doctor_id', 'patient_id', 'notes', 'prescription_name', 'created_by'])) {
            $this->careTicketService->syncPrescription($prescription);
        }
    }

    /**
     * Handle the Prescription "deleted" event.
     */
    public function deleted(Prescription $prescription): void
    {
        $this->careTicketService->cancelBySource(Prescription::class, $prescription->id, 'medication_reminder');
    }

    /**
     * Handle the Prescription "restored" event.
     */
    public function restored(Prescription $prescription): void
    {
        $this->careTicketService->syncPrescription($prescription);
    }
}
