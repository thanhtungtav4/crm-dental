<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\CareTicketService;

class PrescriptionObserver
{
    public function __construct(protected CareTicketService $careTicketService)
    {
    }

    public function created(Prescription $prescription): void
    {
        $this->careTicketService->syncPrescription($prescription);
    }

    public function updated(Prescription $prescription): void
    {
        if ($prescription->wasChanged(['treatment_date', 'doctor_id', 'patient_id', 'notes', 'prescription_name'])) {
            $this->careTicketService->syncPrescription($prescription);
        }
    }

    public function deleted(Prescription $prescription): void
    {
        $this->careTicketService->cancelBySource(Prescription::class, $prescription->id, 'medication_reminder');
    }
}
