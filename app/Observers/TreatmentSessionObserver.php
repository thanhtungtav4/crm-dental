<?php

namespace App\Observers;

use App\Models\TreatmentSession;
use App\Services\CareTicketService;
use App\Services\EmrSyncEventPublisher;
use App\Services\ExamSessionLifecycleService;
use App\Services\TreatmentProgressSyncService;

class TreatmentSessionObserver
{
    public function __construct(
        protected CareTicketService $careTicketService,
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
        protected TreatmentProgressSyncService $treatmentProgressSyncService,
        protected ExamSessionLifecycleService $examSessionLifecycleService,
    ) {}

    public function created(TreatmentSession $session): void
    {
        $this->treatmentProgressSyncService->syncFromTreatmentSession($session);
        $this->careTicketService->syncTreatmentSession($session);
        $this->publishEmrEvent($session, 'treatment_session.created');
    }

    public function updated(TreatmentSession $session): void
    {
        if ($session->wasChanged([
            'performed_at',
            'start_at',
            'end_at',
            'status',
            'doctor_id',
            'assistant_id',
            'plan_item_id',
            'notes',
            'treatment_plan_id',
        ])) {
            $this->treatmentProgressSyncService->syncFromTreatmentSession($session);
            $this->careTicketService->syncTreatmentSession($session);
            $this->publishEmrEvent($session, 'treatment_session.updated');
        }
    }

    public function deleted(TreatmentSession $session): void
    {
        $this->treatmentProgressSyncService->deleteByTreatmentSession($session);
        $this->careTicketService->cancelBySource(TreatmentSession::class, $session->id, 'post_treatment_follow_up');
        $this->examSessionLifecycleService->refresh($session->exam_session_id ? (int) $session->exam_session_id : null);
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
