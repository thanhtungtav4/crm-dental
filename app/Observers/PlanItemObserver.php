<?php

namespace App\Observers;

use App\Models\PlanItem;
use App\Services\CareTicketService;

class PlanItemObserver
{
    public function __construct(protected CareTicketService $careTicketService)
    {
    }

    public function created(PlanItem $planItem): void
    {
        $this->careTicketService->syncPlanItemApproval($planItem);
    }

    public function updated(PlanItem $planItem): void
    {
        if ($planItem->wasChanged(['approval_status', 'approval_decline_reason', 'treatment_plan_id'])) {
            $this->careTicketService->syncPlanItemApproval($planItem);
        }
    }

    public function deleted(PlanItem $planItem): void
    {
        $this->careTicketService->cancelBySource(PlanItem::class, $planItem->id, 'treatment_plan_follow_up');
    }
}
