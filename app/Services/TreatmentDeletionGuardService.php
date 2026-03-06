<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Note;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;

class TreatmentDeletionGuardService
{
    public function canDeleteTreatmentPlan(TreatmentPlan $plan): bool
    {
        return ! $plan->planItems()->withTrashed()->exists()
            && ! $plan->sessions()->withTrashed()->exists()
            && ! $plan->progressDays()->withTrashed()->exists()
            && ! $plan->progressItems()->withTrashed()->exists()
            && ! $plan->invoices()->exists();
    }

    public function canDeletePlanItem(PlanItem $planItem): bool
    {
        return ! $planItem->sessions()->withTrashed()->exists()
            && ! $planItem->progressItems()->withTrashed()->exists()
            && ! $planItem->consents()->exists()
            && ! $planItem->mediaAssets()->exists();
    }

    public function canDeleteTreatmentSession(TreatmentSession $session): bool
    {
        return $session->exam_session_id === null
            && ! $session->progressItem()->withTrashed()->exists()
            && ! $session->materials()->exists()
            && ! $session->invoices()->exists()
            && ! $session->mediaAssets()->exists()
            && ! InventoryTransaction::query()->where('treatment_session_id', $session->id)->exists()
            && ! Prescription::query()->where('treatment_session_id', $session->id)->exists()
            && ! Note::query()
                ->where('source_type', TreatmentSession::class)
                ->where('source_id', $session->id)
                ->exists();
    }
}
