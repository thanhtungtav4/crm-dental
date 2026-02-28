<?php

namespace App\Observers;

use App\Models\ClinicalNote;
use App\Models\ClinicalNoteRevision;

class ClinicalNoteVersionObserver
{
    public function created(ClinicalNote $clinicalNote): void
    {
        ClinicalNoteRevision::query()->firstOrCreate(
            [
                'clinical_note_id' => $clinicalNote->id,
                'version' => (int) ($clinicalNote->lock_version ?: 1),
            ],
            [
                'patient_id' => (int) $clinicalNote->patient_id,
                'visit_episode_id' => $clinicalNote->visit_episode_id ? (int) $clinicalNote->visit_episode_id : null,
                'branch_id' => $clinicalNote->branch_id ? (int) $clinicalNote->branch_id : null,
                'operation' => ClinicalNoteRevision::OPERATION_CREATE,
                'changed_by' => $clinicalNote->created_by ? (int) $clinicalNote->created_by : auth()->id(),
                'previous_payload' => null,
                'current_payload' => $clinicalNote->revisionPayload(),
                'changed_fields' => ClinicalNote::trackedRevisionFields(),
                'reason' => null,
                'created_at' => $clinicalNote->created_at ?? now(),
            ]
        );
    }

    public function updated(ClinicalNote $clinicalNote): void
    {
        if (! $clinicalNote->wasChanged('lock_version')) {
            return;
        }

        $previousPayload = $clinicalNote->revisionPreviousPayload ?? [];
        $currentPayload = $clinicalNote->revisionPayload();
        $changedFields = $this->resolveChangedFields($previousPayload, $currentPayload);

        if (empty($changedFields)) {
            return;
        }

        $operation = $clinicalNote->revisionOperation ?: ClinicalNoteRevision::OPERATION_UPDATE;
        $reason = $clinicalNote->revisionReason;

        ClinicalNoteRevision::query()->create([
            'clinical_note_id' => (int) $clinicalNote->id,
            'patient_id' => (int) $clinicalNote->patient_id,
            'visit_episode_id' => $clinicalNote->visit_episode_id ? (int) $clinicalNote->visit_episode_id : null,
            'branch_id' => $clinicalNote->branch_id ? (int) $clinicalNote->branch_id : null,
            'version' => (int) $clinicalNote->lock_version,
            'operation' => $operation,
            'changed_by' => $clinicalNote->updated_by ? (int) $clinicalNote->updated_by : auth()->id(),
            'previous_payload' => $previousPayload,
            'current_payload' => $currentPayload,
            'changed_fields' => $changedFields,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     * @return array<int, string>
     */
    protected function resolveChangedFields(array $previous, array $current): array
    {
        $changed = [];

        foreach (ClinicalNote::trackedRevisionFields() as $field) {
            $before = data_get($previous, $field);
            $after = data_get($current, $field);

            if (json_encode($before) !== json_encode($after)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
