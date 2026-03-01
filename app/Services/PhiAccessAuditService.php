<?php

namespace App\Services;

use App\Models\EmrAuditLog;
use App\Models\Patient;
use App\Models\PatientMedicalRecord;

class PhiAccessAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function recordPatientWorkspaceRead(Patient $patient, array $context = []): void
    {
        $this->recordRead(
            entityType: EmrAuditLog::ENTITY_PHI_ACCESS,
            entityId: (int) $patient->id,
            patientId: (int) $patient->id,
            visitEpisodeId: null,
            branchId: $patient->first_branch_id ? (int) $patient->first_branch_id : null,
            context: array_merge([
                'resource' => 'patient_exam_treatment_tab',
            ], $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordMedicalRecordRead(PatientMedicalRecord $record, array $context = []): void
    {
        $record->loadMissing('patient:id,first_branch_id');

        $this->recordRead(
            entityType: EmrAuditLog::ENTITY_PHI_ACCESS,
            entityId: (int) $record->id,
            patientId: (int) $record->patient_id,
            visitEpisodeId: null,
            branchId: $record->patient?->first_branch_id ? (int) $record->patient->first_branch_id : null,
            context: array_merge([
                'resource' => 'patient_medical_record',
            ], $context),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function recordRead(
        string $entityType,
        int $entityId,
        int $patientId,
        ?int $visitEpisodeId,
        ?int $branchId,
        array $context = [],
    ): void {
        EmrAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => EmrAuditLog::ACTION_READ,
            'patient_id' => $patientId,
            'visit_episode_id' => $visitEpisodeId,
            'branch_id' => $branchId,
            'actor_id' => auth()->id(),
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
