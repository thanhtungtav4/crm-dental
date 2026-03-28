<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\BranchTransferRequest;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Arr;

class BranchTransferRequestObserver
{
    public function created(BranchTransferRequest $branchTransferRequest): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_BRANCH_TRANSFER,
            entityId: $branchTransferRequest->id,
            action: AuditLog::ACTION_CREATE,
            actorId: is_numeric($branchTransferRequest->requested_by) ? (int) $branchTransferRequest->requested_by : auth()->id(),
            branchId: is_numeric($branchTransferRequest->from_branch_id) ? (int) $branchTransferRequest->from_branch_id : null,
            patientId: is_numeric($branchTransferRequest->patient_id) ? (int) $branchTransferRequest->patient_id : null,
            metadata: array_merge($this->buildMetadata($branchTransferRequest), [
                'status_to' => $branchTransferRequest->status,
            ]),
        );
    }

    public function updated(BranchTransferRequest $branchTransferRequest): void
    {
        if (! $branchTransferRequest->wasChanged('status')) {
            return;
        }

        $managedContext = BranchTransferRequest::currentManagedTransitionContext();
        $managedActorId = data_get($managedContext, 'actor_id');
        $reason = is_string(data_get($managedContext, 'reason'))
            ? (string) data_get($managedContext, 'reason')
            : null;

        $action = match ($branchTransferRequest->status) {
            BranchTransferRequest::STATUS_APPLIED => AuditLog::ACTION_TRANSFER,
            BranchTransferRequest::STATUS_REJECTED => AuditLog::ACTION_FAIL,
            BranchTransferRequest::STATUS_CANCELLED => AuditLog::ACTION_CANCEL,
            default => AuditLog::ACTION_UPDATE,
        };

        AuditLog::record(
            entityType: AuditLog::ENTITY_BRANCH_TRANSFER,
            entityId: $branchTransferRequest->id,
            action: $action,
            actorId: is_numeric($managedActorId)
                ? (int) $managedActorId
                : (is_numeric($branchTransferRequest->decided_by) ? (int) $branchTransferRequest->decided_by : auth()->id()),
            branchId: is_numeric($branchTransferRequest->from_branch_id) ? (int) $branchTransferRequest->from_branch_id : null,
            patientId: is_numeric($branchTransferRequest->patient_id) ? (int) $branchTransferRequest->patient_id : null,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: (string) ($branchTransferRequest->getOriginal('status') ?: BranchTransferRequest::STATUS_PENDING),
                toStatus: $branchTransferRequest->status,
                reason: $reason,
                metadata: array_merge(
                    $this->buildMetadata($branchTransferRequest),
                    Arr::except($managedContext, ['actor_id', 'reason']),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetadata(BranchTransferRequest $branchTransferRequest): array
    {
        return array_filter([
            'patient_id' => $branchTransferRequest->patient_id,
            'from_branch_id' => $branchTransferRequest->from_branch_id,
            'from_branch_name' => $branchTransferRequest->fromBranch?->name,
            'to_branch_id' => $branchTransferRequest->to_branch_id,
            'to_branch_name' => $branchTransferRequest->toBranch?->name,
            'requested_by' => $branchTransferRequest->requested_by,
            'decided_by' => $branchTransferRequest->decided_by,
            'requested_at' => $branchTransferRequest->requested_at?->toDateTimeString(),
            'decided_at' => $branchTransferRequest->decided_at?->toDateTimeString(),
            'applied_at' => $branchTransferRequest->applied_at?->toDateTimeString(),
            'reason' => $branchTransferRequest->reason,
            'note' => $branchTransferRequest->note,
            'metadata_payload' => $branchTransferRequest->metadata,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
