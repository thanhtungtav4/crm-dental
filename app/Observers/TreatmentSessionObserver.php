<?php

namespace App\Observers;

use App\Models\TreatmentSession;
use App\Services\CareTicketService;

class TreatmentSessionObserver
{
    public function __construct(protected CareTicketService $careTicketService)
    {
    }

    public function created(TreatmentSession $session): void
    {
        $this->careTicketService->syncTreatmentSession($session);
    }

    public function updated(TreatmentSession $session): void
    {
        if ($session->wasChanged(['performed_at', 'status', 'doctor_id', 'assistant_id'])) {
            $this->careTicketService->syncTreatmentSession($session);
        }
    }

    public function deleted(TreatmentSession $session): void
    {
        $this->careTicketService->cancelBySource(TreatmentSession::class, $session->id, 'post_treatment_follow_up');
    }
}
