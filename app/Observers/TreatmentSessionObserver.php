<?php

namespace App\Observers;

use App\Models\TreatmentSession;
use App\Services\CareTicketService;
use App\Services\EmrSyncEventPublisher;

class TreatmentSessionObserver
{
    public function __construct(
        protected CareTicketService $careTicketService,
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    public function created(TreatmentSession $session): void
    {
        $this->careTicketService->syncTreatmentSession($session);
        $this->publishEmrEvent($session, 'treatment_session.created');
    }

    public function updated(TreatmentSession $session): void
    {
        if ($session->wasChanged(['performed_at', 'status', 'doctor_id', 'assistant_id'])) {
            $this->careTicketService->syncTreatmentSession($session);
            $this->publishEmrEvent($session, 'treatment_session.updated');
        }
    }

    public function deleted(TreatmentSession $session): void
    {
        $this->careTicketService->cancelBySource(TreatmentSession::class, $session->id, 'post_treatment_follow_up');
        $this->publishEmrEvent($session, 'treatment_session.deleted');
    }

    protected function publishEmrEvent(TreatmentSession $session, string $eventType): void
    {
        $patientId = $session->treatmentPlan?->patient_id;

        if (! $patientId) {
            return;
        }

        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: (int) $patientId,
            eventType: $eventType,
        );
    }
}
