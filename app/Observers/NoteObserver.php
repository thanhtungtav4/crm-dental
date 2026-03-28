<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Note;
use App\Support\WorkflowAuditMetadata;
use Illuminate\Support\Arr;

class NoteObserver
{
    public function updated(Note $note): void
    {
        if (! $note->wasChanged('care_status')) {
            return;
        }

        $managedContext = Note::currentManagedTransitionContext();
        $managedActorId = data_get($managedContext, 'actor_id');
        $fromStatus = Note::normalizeCareStatus((string) $note->getOriginal('care_status'))
            ?? Note::DEFAULT_CARE_STATUS;
        $toStatus = Note::normalizeCareStatus($note->care_status) ?? Note::DEFAULT_CARE_STATUS;

        $action = match ($note->care_status) {
            Note::CARE_STATUS_DONE => AuditLog::ACTION_COMPLETE,
            Note::CARE_STATUS_NEED_FOLLOWUP => AuditLog::ACTION_FOLLOW_UP,
            Note::CARE_STATUS_FAILED => AuditLog::ACTION_FAIL,
            Note::CARE_STATUS_IN_PROGRESS, Note::CARE_STATUS_NOT_STARTED => AuditLog::ACTION_UPDATE,
            default => null,
        };

        if ($action === null) {
            return;
        }

        AuditLog::record(
            entityType: AuditLog::ENTITY_CARE_TICKET,
            entityId: $note->id,
            action: $action,
            actorId: is_numeric($managedActorId) ? (int) $managedActorId : auth()->id(),
            branchId: $note->resolveBranchId(),
            patientId: $note->patient_id,
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                reason: is_string(data_get($managedContext, 'reason')) ? (string) data_get($managedContext, 'reason') : null,
                metadata: array_merge($this->buildMetadata($note, $fromStatus, $toStatus), Arr::except($managedContext, ['actor_id', 'reason'])),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetadata(Note $note, string $fromStatus, string $toStatus): array
    {
        return [
            'patient_id' => $note->patient_id,
            'customer_id' => $note->customer_id,
            'branch_id' => $note->resolveBranchId(),
            'care_type' => $note->care_type,
            'care_channel' => $note->care_channel,
            'ticket_key' => $note->ticket_key,
            'care_status_from' => $fromStatus,
            'care_status_to' => $toStatus,
            'care_at' => $note->care_at?->toDateTimeString(),
            'source_type' => $note->source_type,
            'source_id' => $note->source_id,
        ];
    }
}
