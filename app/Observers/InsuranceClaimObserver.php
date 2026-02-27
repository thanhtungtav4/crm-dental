<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\InsuranceClaim;

class InsuranceClaimObserver
{
    public function created(InsuranceClaim $insuranceClaim): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_INSURANCE_CLAIM,
            entityId: $insuranceClaim->id,
            action: AuditLog::ACTION_CREATE,
            actorId: auth()->id(),
            metadata: $this->buildMetadata($insuranceClaim, null),
        );
    }

    public function updated(InsuranceClaim $insuranceClaim): void
    {
        if (! $insuranceClaim->wasChanged('status')) {
            return;
        }

        $action = match ($insuranceClaim->status) {
            InsuranceClaim::STATUS_APPROVED => AuditLog::ACTION_APPROVE,
            InsuranceClaim::STATUS_DENIED => AuditLog::ACTION_FAIL,
            InsuranceClaim::STATUS_PAID => AuditLog::ACTION_COMPLETE,
            InsuranceClaim::STATUS_CANCELLED => AuditLog::ACTION_CANCEL,
            default => AuditLog::ACTION_UPDATE,
        };

        AuditLog::record(
            entityType: AuditLog::ENTITY_INSURANCE_CLAIM,
            entityId: $insuranceClaim->id,
            action: $action,
            actorId: auth()->id(),
            metadata: $this->buildMetadata($insuranceClaim, (string) $insuranceClaim->getOriginal('status')),
        );
    }

    protected function buildMetadata(InsuranceClaim $insuranceClaim, ?string $fromStatus): array
    {
        return [
            'invoice_id' => $insuranceClaim->invoice_id,
            'patient_id' => $insuranceClaim->patient_id,
            'claim_number' => $insuranceClaim->claim_number,
            'payer_name' => $insuranceClaim->payer_name,
            'amount_claimed' => $insuranceClaim->amount_claimed,
            'amount_approved' => $insuranceClaim->amount_approved,
            'status_from' => $fromStatus,
            'status_to' => $insuranceClaim->status,
            'denial_reason_code' => $insuranceClaim->denial_reason_code,
            'submitted_at' => $insuranceClaim->submitted_at?->toDateTimeString(),
            'approved_at' => $insuranceClaim->approved_at?->toDateTimeString(),
            'denied_at' => $insuranceClaim->denied_at?->toDateTimeString(),
            'paid_at' => $insuranceClaim->paid_at?->toDateTimeString(),
            'cancelled_at' => $insuranceClaim->cancelled_at?->toDateTimeString(),
        ];
    }
}
