<?php

namespace App\Observers;

use App\Models\PlanItem;
use App\Services\CareTicketService;
use App\Services\EmrSyncEventPublisher;

class PlanItemObserver
{
    public function __construct(
        protected CareTicketService $careTicketService,
        protected EmrSyncEventPublisher $emrSyncEventPublisher,
    ) {}

    public function created(PlanItem $planItem): void
    {
        $this->careTicketService->syncPlanItemApproval($planItem);
        $this->publishEmrEvent($planItem, 'plan_item.created');
    }

    public function updated(PlanItem $planItem): void
    {
        if ($planItem->wasChanged(['approval_status', 'approval_decline_reason', 'treatment_plan_id'])) {
            $this->careTicketService->syncPlanItemApproval($planItem);
        }

        if ($planItem->wasChanged([
            'name',
            'service_id',
            'tooth_ids',
            'diagnosis_ids',
            'quantity',
            'final_amount',
            'status',
            'approval_status',
            'required_visits',
            'completed_visits',
        ])) {
            $this->publishEmrEvent($planItem, 'plan_item.updated');
        }
    }

    public function deleted(PlanItem $planItem): void
    {
        $this->careTicketService->cancelBySource(PlanItem::class, $planItem->id, 'treatment_plan_follow_up');
        $this->publishEmrEvent($planItem, 'plan_item.deleted');
    }

    protected function publishEmrEvent(PlanItem $planItem, string $eventType): void
    {
        $patientId = $planItem->treatmentPlan?->patient_id;

        if (! $patientId) {
            return;
        }

        $this->emrSyncEventPublisher->publishForPatientId(
            patientId: (int) $patientId,
            eventType: $eventType,
        );
    }
}
