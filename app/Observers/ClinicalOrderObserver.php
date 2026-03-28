<?php

namespace App\Observers;

use App\Models\ClinicalOrder;
use App\Models\EmrAuditLog;
use App\Services\EmrAuditLogger;
use Illuminate\Support\Arr;

class ClinicalOrderObserver
{
    public function created(ClinicalOrder $clinicalOrder): void
    {
        app(EmrAuditLogger::class)->record(
            entityType: EmrAuditLog::ENTITY_CLINICAL_ORDER,
            entityId: (int) $clinicalOrder->id,
            action: EmrAuditLog::ACTION_CREATE,
            patientId: $clinicalOrder->patient_id ? (int) $clinicalOrder->patient_id : null,
            visitEpisodeId: $clinicalOrder->visit_episode_id ? (int) $clinicalOrder->visit_episode_id : null,
            branchId: $clinicalOrder->branch_id ? (int) $clinicalOrder->branch_id : null,
            actorId: $clinicalOrder->ordered_by ? (int) $clinicalOrder->ordered_by : auth()->id(),
            context: [
                'order_code' => $clinicalOrder->order_code,
                'order_type' => $clinicalOrder->order_type,
                'status' => $clinicalOrder->status,
            ],
        );
    }

    public function updated(ClinicalOrder $clinicalOrder): void
    {
        if (! $clinicalOrder->wasChanged('status')) {
            return;
        }

        $managedContext = ClinicalOrder::currentManagedTransitionContext();
        $managedActorId = data_get($managedContext, 'actor_id');

        $statusTo = (string) $clinicalOrder->status;
        $action = match ($statusTo) {
            ClinicalOrder::STATUS_COMPLETED => EmrAuditLog::ACTION_COMPLETE,
            ClinicalOrder::STATUS_CANCELLED => EmrAuditLog::ACTION_CANCEL,
            default => EmrAuditLog::ACTION_UPDATE,
        };

        app(EmrAuditLogger::class)->record(
            entityType: EmrAuditLog::ENTITY_CLINICAL_ORDER,
            entityId: (int) $clinicalOrder->id,
            action: $action,
            patientId: $clinicalOrder->patient_id ? (int) $clinicalOrder->patient_id : null,
            visitEpisodeId: $clinicalOrder->visit_episode_id ? (int) $clinicalOrder->visit_episode_id : null,
            branchId: $clinicalOrder->branch_id ? (int) $clinicalOrder->branch_id : null,
            actorId: is_numeric($managedActorId)
                ? (int) $managedActorId
                : ($clinicalOrder->ordered_by ? (int) $clinicalOrder->ordered_by : auth()->id()),
            context: array_merge([
                'order_code' => $clinicalOrder->order_code,
                'order_type' => $clinicalOrder->order_type,
                'status_from' => (string) $clinicalOrder->getOriginal('status'),
                'status_to' => $statusTo,
            ], Arr::except($managedContext, ['actor_id'])),
        );
    }
}
