<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\TreatmentSession;

class TreatmentSessionAuditObserver
{
    public function created(TreatmentSession $treatmentSession): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_SESSION,
            entityId: $treatmentSession->id,
            action: AuditLog::ACTION_CREATE,
            actorId: auth()->id() ?? $treatmentSession->created_by,
            metadata: $this->buildMetadata($treatmentSession, null),
        );
    }

    public function updated(TreatmentSession $treatmentSession): void
    {
        if (! $treatmentSession->wasChanged(['status', 'performed_at', 'doctor_id', 'assistant_id'])) {
            return;
        }

        $action = in_array((string) $treatmentSession->status, ['done', 'completed'], true)
            ? AuditLog::ACTION_COMPLETE
            : AuditLog::ACTION_UPDATE;

        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_SESSION,
            entityId: $treatmentSession->id,
            action: $action,
            actorId: auth()->id() ?? $treatmentSession->updated_by,
            metadata: $this->buildMetadata($treatmentSession, (string) $treatmentSession->getOriginal('status')),
        );
    }

    public function deleted(TreatmentSession $treatmentSession): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_SESSION,
            entityId: $treatmentSession->id,
            action: AuditLog::ACTION_CANCEL,
            actorId: auth()->id() ?? $treatmentSession->updated_by,
            metadata: $this->buildMetadata($treatmentSession, (string) $treatmentSession->status),
        );
    }

    protected function buildMetadata(TreatmentSession $treatmentSession, ?string $fromStatus): array
    {
        return [
            'treatment_plan_id' => $treatmentSession->treatment_plan_id,
            'plan_item_id' => $treatmentSession->plan_item_id,
            'doctor_id' => $treatmentSession->doctor_id,
            'assistant_id' => $treatmentSession->assistant_id,
            'status_from' => $fromStatus,
            'status_to' => $treatmentSession->status,
            'start_at' => $treatmentSession->start_at?->toDateTimeString(),
            'end_at' => $treatmentSession->end_at?->toDateTimeString(),
            'performed_at' => $treatmentSession->performed_at?->toDateTimeString(),
        ];
    }
}
