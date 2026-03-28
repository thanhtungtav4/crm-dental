<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Support\WorkflowAuditMetadata;

class TreatmentSessionAuditObserver
{
    public function created(TreatmentSession $treatmentSession): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_SESSION,
            entityId: $treatmentSession->id,
            action: AuditLog::ACTION_CREATE,
            actorId: auth()->id() ?? $treatmentSession->created_by,
            branchId: $this->resolveBranchId($treatmentSession),
            patientId: $this->resolvePatientId($treatmentSession),
            metadata: array_merge($this->buildMetadata($treatmentSession), [
                'patient_id' => $this->resolvePatientId($treatmentSession),
                'branch_id' => $this->resolveBranchId($treatmentSession),
                'status_to' => $treatmentSession->status,
            ]),
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
            branchId: $this->resolveBranchId($treatmentSession),
            patientId: $this->resolvePatientId($treatmentSession),
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: (string) ($treatmentSession->getOriginal('status') ?: 'scheduled'),
                toStatus: (string) $treatmentSession->status,
                reason: $treatmentSession->evidence_override_reason,
                metadata: array_merge($this->buildMetadata($treatmentSession), [
                    'patient_id' => $this->resolvePatientId($treatmentSession),
                    'branch_id' => $this->resolveBranchId($treatmentSession),
                ]),
            ),
        );
    }

    public function deleted(TreatmentSession $treatmentSession): void
    {
        AuditLog::record(
            entityType: AuditLog::ENTITY_TREATMENT_SESSION,
            entityId: $treatmentSession->id,
            action: AuditLog::ACTION_CANCEL,
            actorId: auth()->id() ?? $treatmentSession->updated_by,
            branchId: $this->resolveBranchId($treatmentSession),
            patientId: $this->resolvePatientId($treatmentSession),
            metadata: WorkflowAuditMetadata::transition(
                fromStatus: (string) $treatmentSession->status,
                toStatus: (string) $treatmentSession->status,
                metadata: array_merge($this->buildMetadata($treatmentSession), [
                    'patient_id' => $this->resolvePatientId($treatmentSession),
                    'branch_id' => $this->resolveBranchId($treatmentSession),
                ]),
            ),
        );
    }

    protected function buildMetadata(TreatmentSession $treatmentSession): array
    {
        return [
            'treatment_plan_id' => $treatmentSession->treatment_plan_id,
            'plan_item_id' => $treatmentSession->plan_item_id,
            'doctor_id' => $treatmentSession->doctor_id,
            'assistant_id' => $treatmentSession->assistant_id,
            'evidence_override_reason' => $treatmentSession->evidence_override_reason,
            'evidence_override_by' => $treatmentSession->evidence_override_by,
            'evidence_override_at' => $treatmentSession->evidence_override_at?->toDateTimeString(),
            'start_at' => $treatmentSession->start_at?->toDateTimeString(),
            'end_at' => $treatmentSession->end_at?->toDateTimeString(),
            'performed_at' => $treatmentSession->performed_at?->toDateTimeString(),
        ];
    }

    protected function resolvePatientId(TreatmentSession $treatmentSession): ?int
    {
        $patientId = $treatmentSession->treatmentPlan?->patient_id;

        if (! is_numeric($patientId) && is_numeric($treatmentSession->treatment_plan_id)) {
            $patientId = TreatmentPlan::query()
                ->whereKey((int) $treatmentSession->treatment_plan_id)
                ->value('patient_id');
        }

        return is_numeric($patientId) ? (int) $patientId : null;
    }

    protected function resolveBranchId(TreatmentSession $treatmentSession): ?int
    {
        $branchId = $treatmentSession->resolveBranchId();

        if (! is_numeric($branchId) && is_numeric($treatmentSession->treatment_plan_id)) {
            $branchId = TreatmentPlan::query()
                ->whereKey((int) $treatmentSession->treatment_plan_id)
                ->value('branch_id');
        }

        return is_numeric($branchId) ? (int) $branchId : null;
    }
}
