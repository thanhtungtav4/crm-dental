<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\MasterPatientDuplicate;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Arr;

class MasterPatientDuplicateObserver
{
    public function updated(MasterPatientDuplicate $masterPatientDuplicate): void
    {
        if (! $masterPatientDuplicate->wasChanged('status')) {
            return;
        }

        $managedContext = MasterPatientDuplicate::currentManagedTransitionContext();
        $managedActorId = data_get($managedContext, 'actor_id');
        $fromStatus = MasterPatientDuplicate::normalizeStatus((string) $masterPatientDuplicate->getOriginal('status'))
            ?? MasterPatientDuplicate::STATUS_OPEN;
        $toStatus = MasterPatientDuplicate::normalizeStatus((string) $masterPatientDuplicate->status)
            ?? MasterPatientDuplicate::STATUS_OPEN;

        $action = is_string(data_get($managedContext, 'audit_action'))
            ? (string) data_get($managedContext, 'audit_action')
            : ($toStatus === MasterPatientDuplicate::STATUS_OPEN ? AuditLog::ACTION_ROLLBACK : AuditLog::ACTION_RESOLVE);

        AuditLog::record(
            entityType: AuditLog::ENTITY_MASTER_PATIENT_DUPLICATE,
            entityId: (int) $masterPatientDuplicate->id,
            action: $action,
            actorId: is_numeric($managedActorId)
                ? (int) $managedActorId
                : (is_numeric($masterPatientDuplicate->reviewed_by) ? (int) $masterPatientDuplicate->reviewed_by : auth()->id()),
            branchId: is_numeric($masterPatientDuplicate->branch_id) ? (int) $masterPatientDuplicate->branch_id : null,
            patientId: is_numeric($masterPatientDuplicate->patient_id) ? (int) $masterPatientDuplicate->patient_id : null,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                reason: is_string(data_get($managedContext, 'reason')) ? (string) data_get($managedContext, 'reason') : null,
                metadata: array_merge(
                    $this->buildMetadata($masterPatientDuplicate),
                    Arr::except($managedContext, ['actor_id', 'reason', 'audit_action']),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetadata(MasterPatientDuplicate $masterPatientDuplicate): array
    {
        return array_filter([
            'patient_id' => is_numeric($masterPatientDuplicate->patient_id) ? (int) $masterPatientDuplicate->patient_id : null,
            'branch_id' => is_numeric($masterPatientDuplicate->branch_id) ? (int) $masterPatientDuplicate->branch_id : null,
            'identity_type' => $masterPatientDuplicate->identity_type,
            'identity_hash' => $masterPatientDuplicate->identity_hash,
            'identity_value' => $masterPatientDuplicate->identity_value,
            'matched_patient_ids' => $masterPatientDuplicate->matchedPatientIds(),
            'matched_branch_ids' => $masterPatientDuplicate->matchedBranchIds(),
            'confidence_score' => $masterPatientDuplicate->confidence_score !== null
                ? (float) $masterPatientDuplicate->confidence_score
                : null,
            'review_note' => $masterPatientDuplicate->review_note,
            'reviewed_by' => is_numeric($masterPatientDuplicate->reviewed_by) ? (int) $masterPatientDuplicate->reviewed_by : null,
            'reviewed_at' => $masterPatientDuplicate->reviewed_at?->toDateTimeString(),
            'metadata_payload' => $masterPatientDuplicate->metadata,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
