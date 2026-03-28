<?php

namespace App\Services;

use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamSessionWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     */
    public function synchronizeSnapshot(
        ExamSession $examSession,
        array $attributes = [],
        ?string $targetStatus = null,
        array $context = [],
    ): ExamSession {
        return DB::transaction(function () use ($examSession, $attributes, $targetStatus, $context): ExamSession {
            $lockedExamSession = $this->lockExamSession($examSession);
            $currentStatus = $this->normalizeStatus((string) ($lockedExamSession->status ?: ExamSession::STATUS_DRAFT));
            $normalizedTargetStatus = $this->normalizeStatus($targetStatus);

            $lockedExamSession->fill($attributes);

            if (
                $normalizedTargetStatus !== null
                && $currentStatus !== ExamSession::STATUS_LOCKED
                && $normalizedTargetStatus !== $currentStatus
                && ExamSession::canTransition($currentStatus, $normalizedTargetStatus)
            ) {
                $lockedExamSession->status = $normalizedTargetStatus;
            }

            if (! $lockedExamSession->isDirty()) {
                return $lockedExamSession;
            }

            if ($lockedExamSession->isDirty('status')) {
                return $this->saveWithinManagedWorkflow($lockedExamSession, $context);
            }

            $lockedExamSession->save();

            return $lockedExamSession->fresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $context
     */
    public function transition(
        ExamSession $examSession,
        string $targetStatus,
        array $attributes = [],
        array $context = [],
    ): ExamSession {
        return DB::transaction(function () use ($examSession, $targetStatus, $attributes, $context): ExamSession {
            $lockedExamSession = $this->lockExamSession($examSession);
            $fromStatus = $this->normalizeStatus((string) ($lockedExamSession->status ?: ExamSession::STATUS_DRAFT));
            $normalizedTargetStatus = $this->normalizeStatus($targetStatus);

            if (! ExamSession::canTransition($fromStatus, $normalizedTargetStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'EXAM_SESSION_STATE_INVALID: Không thể chuyển trạng thái phiên khám.',
                ]);
            }

            $lockedExamSession->fill(array_merge($attributes, [
                'status' => $normalizedTargetStatus,
            ]));

            if (! $lockedExamSession->isDirty()) {
                return $lockedExamSession;
            }

            return $this->saveWithinManagedWorkflow($lockedExamSession, $context);
        }, 3);
    }

    protected function lockExamSession(ExamSession $examSession): ExamSession
    {
        return ExamSession::query()
            ->lockForUpdate()
            ->findOrFail($examSession->getKey());
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function saveWithinManagedWorkflow(ExamSession $examSession, array $context = []): ExamSession
    {
        ExamSession::runWithinManagedWorkflow(function () use ($examSession): void {
            $examSession->save();
        }, $context);

        return $examSession->fresh();
    }

    protected function normalizeStatus(?string $status): string
    {
        $normalizedStatus = strtolower(trim((string) $status));

        if (! in_array($normalizedStatus, ExamSession::allStatuses(), true)) {
            return ExamSession::STATUS_DRAFT;
        }

        return $normalizedStatus;
    }
}
