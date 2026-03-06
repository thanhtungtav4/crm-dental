<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentProgressItem;
use App\Models\TreatmentSession;
use App\Models\VisitEpisode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TreatmentProgressSyncService
{
    public function __construct(
        protected ExamSessionLifecycleService $examSessionLifecycleService,
        protected ExamSessionProvisioningService $examSessionProvisioningService,
    ) {}

    public function syncFromTreatmentSession(TreatmentSession $session): ?TreatmentProgressItem
    {
        return DB::transaction(function () use ($session): ?TreatmentProgressItem {
            $lockedSession = TreatmentSession::query()
                ->with([
                    'treatmentPlan:id,patient_id,branch_id',
                    'planItem:id,treatment_plan_id,name,tooth_number,quantity,price,final_amount',
                ])
                ->lockForUpdate()
                ->find($session->getKey());

            if (! $lockedSession) {
                return null;
            }

            $patientId = $lockedSession->treatmentPlan?->patient_id;
            if (! $patientId) {
                return null;
            }

            $progressAt = $this->resolveProgressDateTime($lockedSession);
            $progressDate = $progressAt->toDateString();
            $status = $this->normalizeItemStatus((string) ($lockedSession->status ?? 'scheduled'));
            $examSession = $this->resolveExamSession($lockedSession, (int) $patientId, $progressDate);
            $this->primeExamSessionStatus($examSession, $status);

            if (! $lockedSession->exam_session_id || (int) $lockedSession->exam_session_id !== (int) $examSession->id) {
                $lockedSession->exam_session_id = $examSession->id;
                $lockedSession->saveQuietly();
            }
            $day = TreatmentProgressDay::query()
                ->where('patient_id', (int) $patientId)
                ->where('exam_session_id', (int) $examSession->id)
                ->whereDate('progress_date', $progressDate)
                ->lockForUpdate()
                ->first();

            if (! $day) {
                $day = TreatmentProgressDay::query()->create([
                    'patient_id' => (int) $patientId,
                    'exam_session_id' => (int) $examSession->id,
                    'progress_date' => $progressDate,
                    'treatment_plan_id' => $lockedSession->treatment_plan_id ? (int) $lockedSession->treatment_plan_id : null,
                    'branch_id' => $lockedSession->treatmentPlan?->branch_id ? (int) $lockedSession->treatmentPlan->branch_id : null,
                    'status' => TreatmentProgressDay::STATUS_PLANNED,
                    'created_by' => $lockedSession->created_by,
                    'updated_by' => $lockedSession->updated_by,
                ]);
            } elseif ($day->status !== TreatmentProgressDay::STATUS_LOCKED) {
                $day->fill([
                    'treatment_plan_id' => $lockedSession->treatment_plan_id ? (int) $lockedSession->treatment_plan_id : null,
                    'branch_id' => $lockedSession->treatmentPlan?->branch_id ? (int) $lockedSession->treatmentPlan->branch_id : null,
                    'updated_by' => $lockedSession->updated_by,
                ]);
                $day->save();
            }

            $item = TreatmentProgressItem::query()
                ->where('treatment_session_id', $lockedSession->id)
                ->lockForUpdate()
                ->first();

            if ($day->status === TreatmentProgressDay::STATUS_LOCKED) {
                return $item?->fresh(['progressDay', 'examSession']);
            }

            $quantity = max(1, (float) ($lockedSession->planItem?->quantity ?? 1));
            $unitPrice = (float) ($lockedSession->planItem?->price ?? 0);
            $totalAmount = (float) ($lockedSession->planItem?->final_amount ?? ($quantity * $unitPrice));

            if (! $item) {
                $item = new TreatmentProgressItem([
                    'treatment_session_id' => $lockedSession->id,
                    'created_by' => $lockedSession->created_by,
                ]);
            }

            $item->fill([
                'treatment_progress_day_id' => $day->id,
                'patient_id' => (int) $patientId,
                'exam_session_id' => (int) $examSession->id,
                'treatment_plan_id' => $lockedSession->treatment_plan_id ? (int) $lockedSession->treatment_plan_id : null,
                'plan_item_id' => $lockedSession->plan_item_id ? (int) $lockedSession->plan_item_id : null,
                'doctor_id' => $lockedSession->doctor_id ? (int) $lockedSession->doctor_id : null,
                'assistant_id' => $lockedSession->assistant_id ? (int) $lockedSession->assistant_id : null,
                'tooth_number' => $lockedSession->planItem?->tooth_number,
                'procedure_name' => $lockedSession->planItem?->name ?: $lockedSession->procedure,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'status' => $status,
                'performed_at' => $progressAt,
                'notes' => $lockedSession->notes,
                'updated_by' => $lockedSession->updated_by,
            ]);
            $item->save();

            $day->refresh();
            $this->refreshProgressDayStatus($day);
            $this->examSessionLifecycleService->refresh((int) $examSession->id);

            return $item->fresh(['progressDay', 'examSession']);
        }, 3);
    }

    public function deleteByTreatmentSession(TreatmentSession $session): void
    {
        DB::transaction(function () use ($session): void {
            $lockedSession = TreatmentSession::query()
                ->lockForUpdate()
                ->find($session->getKey());

            $item = TreatmentProgressItem::query()
                ->where('treatment_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            $examSessionId = $item?->exam_session_id ?: $lockedSession?->exam_session_id;
            $day = $item?->progressDay()
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->delete();
            }

            if ($day) {
                $remainingItems = $day->items()->count();

                if ($remainingItems === 0 && $day->status !== TreatmentProgressDay::STATUS_LOCKED) {
                    $day->delete();
                } else {
                    $this->refreshProgressDayStatus($day);
                }
            }

            if ($examSessionId) {
                $this->examSessionLifecycleService->refresh((int) $examSessionId);
            }
        }, 3);
    }

    protected function refreshProgressDayStatus(TreatmentProgressDay $day): void
    {
        if ($day->status === TreatmentProgressDay::STATUS_LOCKED) {
            return;
        }

        $stats = $day->items()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $stats->sum();
        if ($total === 0) {
            $day->status = TreatmentProgressDay::STATUS_PLANNED;
            $day->started_at = null;
            $day->completed_at = null;
            $day->save();

            return;
        }

        $completed = (int) ($stats[TreatmentProgressItem::STATUS_COMPLETED] ?? 0);
        $inProgress = (int) ($stats[TreatmentProgressItem::STATUS_IN_PROGRESS] ?? 0);
        $planned = (int) ($stats[TreatmentProgressItem::STATUS_PLANNED] ?? 0);

        if ($completed === $total) {
            $day->status = TreatmentProgressDay::STATUS_COMPLETED;
            $day->started_at ??= now();
            $day->completed_at ??= now();
        } elseif (($inProgress + $completed) > 0) {
            $day->status = TreatmentProgressDay::STATUS_IN_PROGRESS;
            $day->started_at ??= now();
            $day->completed_at = null;
        } elseif ($planned > 0) {
            $day->status = TreatmentProgressDay::STATUS_PLANNED;
            $day->started_at = null;
            $day->completed_at = null;
        }

        $day->save();
    }

    protected function resolveProgressDateTime(TreatmentSession $session): Carbon
    {
        $value = $session->performed_at ?? $session->start_at ?? $session->end_at ?? $session->created_at;

        return $value ? Carbon::parse($value) : now();
    }

    protected function resolveExamSession(TreatmentSession $session, int $patientId, string $progressDate): ExamSession
    {
        if ($session->exam_session_id) {
            $existing = ExamSession::query()
                ->lockForUpdate()
                ->find((int) $session->exam_session_id);

            if ($existing) {
                return $existing;
            }
        }

        $resolved = $this->examSessionProvisioningService->resolveForPatientOnDate(
            patientId: $patientId,
            branchId: $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
            date: $progressDate,
            doctorId: $session->doctor_id ? (int) $session->doctor_id : null,
            visitEpisodeId: $this->resolveVisitEpisodeId(
                patientId: $patientId,
                branchId: $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
                progressDate: $progressDate,
            ),
            createIfMissing: true,
        );

        return ExamSession::query()
            ->lockForUpdate()
            ->findOrFail((int) $resolved?->id);
    }

    protected function normalizeItemStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'done', 'completed' => TreatmentProgressItem::STATUS_COMPLETED,
            'follow_up', 'follow-up', 'in_progress', 'in progress' => TreatmentProgressItem::STATUS_IN_PROGRESS,
            'cancelled', 'canceled' => TreatmentProgressItem::STATUS_CANCELLED,
            default => TreatmentProgressItem::STATUS_PLANNED,
        };
    }

    protected function primeExamSessionStatus(ExamSession $examSession, string $itemStatus): void
    {
        $targetStatus = match ($itemStatus) {
            TreatmentProgressItem::STATUS_COMPLETED, TreatmentProgressItem::STATUS_IN_PROGRESS => ExamSession::STATUS_IN_PROGRESS,
            TreatmentProgressItem::STATUS_PLANNED => ExamSession::STATUS_PLANNED,
            default => null,
        };

        if ($targetStatus === null || $examSession->status !== ExamSession::STATUS_DRAFT) {
            return;
        }

        if (! ExamSession::canTransition((string) $examSession->status, $targetStatus)) {
            return;
        }

        $examSession->status = $targetStatus;
        $examSession->saveQuietly();
    }

    protected function resolveVisitEpisodeId(int $patientId, ?int $branchId, string $progressDate): ?int
    {
        $query = VisitEpisode::query()
            ->where('patient_id', $patientId)
            ->whereDate('scheduled_at', $progressDate);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $episodeId = $query
            ->orderByRaw('appointment_id IS NULL')
            ->orderByDesc('scheduled_at')
            ->value('id');

        return $episodeId !== null ? (int) $episodeId : null;
    }
}
