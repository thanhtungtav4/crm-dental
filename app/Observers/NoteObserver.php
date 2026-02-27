<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Note;

class NoteObserver
{
    public function updated(Note $note): void
    {
        if (! $note->wasChanged('care_status')) {
            return;
        }

        $actorId = auth()->id();

        if (! $actorId) {
            return;
        }

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
            actorId: $actorId,
            metadata: [
                'patient_id' => $note->patient_id,
                'customer_id' => $note->customer_id,
                'branch_id' => $note->resolveBranchId(),
                'care_type' => $note->care_type,
                'care_channel' => $note->care_channel,
                'care_status_from' => Note::normalizeCareStatus((string) $note->getOriginal('care_status'))
                    ?? Note::DEFAULT_CARE_STATUS,
                'care_status_to' => $note->care_status,
                'care_at' => $note->care_at?->toDateTimeString(),
                'source_type' => $note->source_type,
                'source_id' => $note->source_id,
            ]
        );
    }
}
