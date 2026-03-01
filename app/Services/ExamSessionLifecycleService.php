<?php

namespace App\Services;

use App\Models\ClinicalResult;
use App\Models\ExamSession;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentProgressItem;

class ExamSessionLifecycleService
{
    public function refresh(?int $examSessionId): void
    {
        if (! $examSessionId) {
            return;
        }

        $session = ExamSession::query()
            ->with([
                'clinicalNote',
                'treatmentProgressDays:id,exam_session_id,status',
            ])
            ->find($examSessionId);

        if (! $session) {
            return;
        }

        if ($session->status === ExamSession::STATUS_LOCKED) {
            $this->lockProgressDays($session->id);

            return;
        }

        $targetStatus = $this->resolveTargetStatus($session);
        if ($targetStatus === $session->status) {
            if ($targetStatus === ExamSession::STATUS_LOCKED) {
                $this->lockProgressDays($session->id);
            }

            return;
        }

        if ($targetStatus === ExamSession::STATUS_LOCKED) {
            $session->status = ExamSession::STATUS_LOCKED;
            $session->saveQuietly();
            $this->lockProgressDays($session->id);

            return;
        }

        if (! ExamSession::canTransition((string) $session->status, $targetStatus)) {
            return;
        }

        $session->status = $targetStatus;
        $session->saveQuietly();
    }

    protected function resolveTargetStatus(ExamSession $session): string
    {
        $hasPrescription = $session->prescriptions()->exists();
        $hasFinalResult = ClinicalResult::query()
            ->whereIn('status', [ClinicalResult::STATUS_FINAL, ClinicalResult::STATUS_AMENDED])
            ->whereHas('clinicalOrder', function ($query) use ($session): void {
                $query->where('exam_session_id', $session->id);
            })
            ->exists();

        if ($hasPrescription || $hasFinalResult) {
            return ExamSession::STATUS_LOCKED;
        }

        $progressStatusStats = TreatmentProgressDay::query()
            ->where('exam_session_id', $session->id)
            ->whereNull('deleted_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalDays = (int) $progressStatusStats->sum();
        $lockedDays = (int) ($progressStatusStats[TreatmentProgressDay::STATUS_LOCKED] ?? 0);
        $completedDays = (int) ($progressStatusStats[TreatmentProgressDay::STATUS_COMPLETED] ?? 0);
        $inProgressDays = (int) ($progressStatusStats[TreatmentProgressDay::STATUS_IN_PROGRESS] ?? 0);
        $plannedDays = (int) ($progressStatusStats[TreatmentProgressDay::STATUS_PLANNED] ?? 0);

        if ($lockedDays > 0) {
            return ExamSession::STATUS_LOCKED;
        }

        if ($totalDays > 0 && $completedDays === $totalDays) {
            return ExamSession::STATUS_COMPLETED;
        }

        if (($inProgressDays + $completedDays + $plannedDays) > 0) {
            return ExamSession::STATUS_IN_PROGRESS;
        }

        $hasProgressItems = TreatmentProgressItem::query()
            ->where('exam_session_id', $session->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasProgressItems || $this->hasClinicalPayload($session)) {
            return ExamSession::STATUS_IN_PROGRESS;
        }

        if ($session->visit_episode_id || $session->doctor_id) {
            return ExamSession::STATUS_PLANNED;
        }

        return ExamSession::STATUS_DRAFT;
    }

    protected function hasClinicalPayload(ExamSession $session): bool
    {
        $note = $session->clinicalNote;

        if (! $note) {
            return false;
        }

        return filled($note->general_exam_notes)
            || filled($note->treatment_plan_note)
            || filled($note->other_diagnosis)
            || ! empty(array_filter((array) ($note->indications ?? []), fn ($item) => filled($item)))
            || ! empty(array_filter((array) ($note->tooth_diagnosis_data ?? []), fn ($item) => filled($item)));
    }

    protected function lockProgressDays(int $examSessionId): void
    {
        TreatmentProgressDay::query()
            ->where('exam_session_id', $examSessionId)
            ->where('status', '!=', TreatmentProgressDay::STATUS_LOCKED)
            ->update([
                'status' => TreatmentProgressDay::STATUS_LOCKED,
                'locked_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
