<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Arr;

class InsuranceClaimObserver
{
    public function created(InsuranceClaim $insuranceClaim): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_INSURANCE_CLAIM,
            entityId: $insuranceClaim->id,
            action: AuditLog::ACTION_CREATE,
            actorId: auth()->id(),
            branchId: $this->resolveBranchId($insuranceClaim),
            patientId: $insuranceClaim->patient_id,
            metadata: array_merge($this->buildMetadata($insuranceClaim), [
                'status_to' => $insuranceClaim->status,
            ]),
        );
    }

    public function updated(InsuranceClaim $insuranceClaim): void
    {
        if (! $insuranceClaim->wasChanged('status')) {
            return;
        }

        $managedContext = InsuranceClaim::currentManagedTransitionContext();
        $managedActorId = data_get($managedContext, 'actor_id');
        $reason = is_string(data_get($managedContext, 'reason'))
            ? (string) data_get($managedContext, 'reason')
            : null;

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
            actorId: is_numeric($managedActorId) ? (int) $managedActorId : auth()->id(),
            branchId: $this->resolveBranchId($insuranceClaim),
            patientId: $insuranceClaim->patient_id,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: (string) ($insuranceClaim->getOriginal('status') ?: InsuranceClaim::STATUS_DRAFT),
                toStatus: $insuranceClaim->status,
                reason: $reason,
                metadata: array_merge(
                    $this->buildMetadata($insuranceClaim),
                    Arr::except($managedContext, ['actor_id', 'reason']),
                ),
            ),
        );
    }

    protected function buildMetadata(InsuranceClaim $insuranceClaim): array
    {
        return [
            'invoice_id' => $insuranceClaim->invoice_id,
            'patient_id' => $insuranceClaim->patient_id,
            'claim_number' => $insuranceClaim->claim_number,
            'payer_name' => $insuranceClaim->payer_name,
            'amount_claimed' => $insuranceClaim->amount_claimed,
            'amount_approved' => $insuranceClaim->amount_approved,
            'denial_reason_code' => $insuranceClaim->denial_reason_code,
            'submitted_at' => $insuranceClaim->submitted_at?->toDateTimeString(),
            'approved_at' => $insuranceClaim->approved_at?->toDateTimeString(),
            'denied_at' => $insuranceClaim->denied_at?->toDateTimeString(),
            'paid_at' => $insuranceClaim->paid_at?->toDateTimeString(),
            'cancelled_at' => $insuranceClaim->cancelled_at?->toDateTimeString(),
        ];
    }

    protected function resolveBranchId(InsuranceClaim $insuranceClaim): ?int
    {
        $branchId = $insuranceClaim->invoice?->branch_id;

        if (! is_numeric($branchId) && is_numeric($insuranceClaim->invoice_id)) {
            $branchId = Invoice::query()
                ->whereKey((int) $insuranceClaim->invoice_id)
                ->value('branch_id');
        }

        return is_numeric($branchId) ? (int) $branchId : null;
    }
}
