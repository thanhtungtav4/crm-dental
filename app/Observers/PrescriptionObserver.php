<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\CareTicketService;
use App\Services\EmrSyncEventPublisher;

class PrescriptionObserver
{
    public function __construct(
        protected CareTicketService $careTicketService,
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        $this->careTicketService->syncPrescription($prescription);
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $prescription->patient_id,
            eventType: 'prescription.created',
        );
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        if ($prescription->wasChanged(['treatment_date', 'doctor_id', 'patient_id', 'notes', 'prescription_name', 'created_by'])) {
            $this->careTicketService->syncPrescription($prescription);
            $this->emrSyncEventPublisher->publishForPatientId(
                patientId: $prescription->patient_id,
                eventType: 'prescription.updated',
            );
        }
    }

    /**
     * Handle the Prescription "deleted" event.
     */
    public function deleted(Prescription $prescription): void
    {
        $this->careTicketService->cancelBySource(Prescription::class, $prescription->id, 'medication_reminder');
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $prescription->patient_id,
            eventType: 'prescription.deleted',
        );
    }

    /**
     * Handle the Prescription "restored" event.
     */
    public function restored(Prescription $prescription): void
    {
        $this->careTicketService->syncPrescription($prescription);
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $prescription->patient_id,
            eventType: 'prescription.restored',
        );
    }
}
