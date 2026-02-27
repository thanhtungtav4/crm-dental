<?php

namespace App\Observers;

use App\Models\TreatmentPlan;
use App\Services\EmrSyncEventPublisher;

class TreatmentPlanObserver
{
    public function __construct(
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    public function created(TreatmentPlan $treatmentPlan): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $treatmentPlan->patient_id,
            eventType: 'treatment_plan.created',
        );
    }

    public function updated(TreatmentPlan $treatmentPlan): void
    {
        if (! $treatmentPlan->wasChanged()) {
            return;
        }

        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $treatmentPlan->patient_id,
            eventType: 'treatment_plan.updated',
        );
    }

    public function deleted(TreatmentPlan $treatmentPlan): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $treatmentPlan->patient_id,
            eventType: 'treatment_plan.deleted',
        );
    }

    public function restored(TreatmentPlan $treatmentPlan): void
    {
        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: $treatmentPlan->patient_id,
            eventType: 'treatment_plan.restored',
        );
    }
}
