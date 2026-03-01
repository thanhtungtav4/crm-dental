<?php

namespace App\Observers;

use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\EmrAuditLog;
use App\Services\EmrAuditLogger;
use App\Services\ExamSessionLifecycleService;

class ClinicalResultObserver
{
    public function __construct(protected ExamSessionLifecycleService $examSessionLifecycleService) {}

    public function created(ClinicalResult $clinicalResult): void
    {
        app(EmrAuditLogger::class)->record(
            entityType: EmrAuditLog::ENTITY_CLINICAL_RESULT,
            entityId: (int) $clinicalResult->id,
            action: EmrAuditLog::ACTION_CREATE,
            patientId: $clinicalResult->patient_id ? (int) $clinicalResult->patient_id : null,
            visitEpisodeId: $clinicalResult->visit_episode_id ? (int) $clinicalResult->visit_episode_id : null,
            branchId: $clinicalResult->branch_id ? (int) $clinicalResult->branch_id : null,
            actorId: $clinicalResult->verified_by ? (int) $clinicalResult->verified_by : auth()->id(),
            context: [
                'result_code' => $clinicalResult->result_code,
                'clinical_order_id' => $clinicalResult->clinical_order_id,
                'status' => $clinicalResult->status,
            ],
        );

        $this->syncExamSessionLifecycle($clinicalResult);
    }

    public function updated(ClinicalResult $clinicalResult): void
    {
        if (! $clinicalResult->wasChanged('status')) {
            return;
        }

        $statusTo = (string) $clinicalResult->status;
        $action = match ($statusTo) {
            ClinicalResult::STATUS_FINAL => EmrAuditLog::ACTION_FINALIZE,
            ClinicalResult::STATUS_AMENDED => EmrAuditLog::ACTION_AMEND,
            default => EmrAuditLog::ACTION_UPDATE,
        };

        app(EmrAuditLogger::class)->record(
            entityType: EmrAuditLog::ENTITY_CLINICAL_RESULT,
            entityId: (int) $clinicalResult->id,
            action: $action,
            patientId: $clinicalResult->patient_id ? (int) $clinicalResult->patient_id : null,
            visitEpisodeId: $clinicalResult->visit_episode_id ? (int) $clinicalResult->visit_episode_id : null,
            branchId: $clinicalResult->branch_id ? (int) $clinicalResult->branch_id : null,
            actorId: $clinicalResult->verified_by ? (int) $clinicalResult->verified_by : auth()->id(),
            context: [
                'result_code' => $clinicalResult->result_code,
                'clinical_order_id' => $clinicalResult->clinical_order_id,
                'status_from' => (string) $clinicalResult->getOriginal('status'),
                'status_to' => $statusTo,
            ],
        );

        $this->syncExamSessionLifecycle($clinicalResult);
    }

    protected function syncExamSessionLifecycle(ClinicalResult $clinicalResult): void
    {
        $examSessionId = $clinicalResult->clinicalOrder?->exam_session_id;

        if (! $examSessionId && $clinicalResult->clinical_order_id) {
            $examSessionId = ClinicalOrder::query()
                ->whereKey((int) $clinicalResult->clinical_order_id)
                ->value('exam_session_id');
        }

        $this->examSessionLifecycleService->refresh($examSessionId ? (int) $examSessionId : null);
    }
}
