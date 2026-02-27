<?php

namespace App\Observers;

use App\Models\PatientMedicalRecord;
use App\Services\EmrSyncEventPublisher;

class PatientMedicalRecordObserver
{
    public function __construct(
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    public function created(PatientMedicalRecord $patientMedicalRecord): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $patientMedicalRecord->patient_id,
            eventType: 'medical_record.created',
        );
    }

    public function updated(PatientMedicalRecord $patientMedicalRecord): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $patientMedicalRecord->patient_id,
            eventType: 'medical_record.updated',
        );
    }

    public function deleted(PatientMedicalRecord $patientMedicalRecord): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $patientMedicalRecord->patient_id,
            eventType: 'medical_record.deleted',
        );
    }

    public function restored(PatientMedicalRecord $patientMedicalRecord): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $patientMedicalRecord->patient_id,
            eventType: 'medical_record.restored',
        );
    }
}
