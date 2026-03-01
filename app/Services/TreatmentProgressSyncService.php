<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\TreatmentProgressDay;
use App\Models\TreatmentProgressItem;
use App\Models\TreatmentSession;
use App\Models\VisitEpisode;
use Illuminate\Support\Carbon;

class TreatmentProgressSyncService
{
    public function __construct(protected ExamSessionLifecycleService $examSessionLifecycleService) {}

    public function syncFromTreatmentSession(TreatmentSession $session): ?TreatmentProgressItem
    {
        $session->loadMissing([
            'treatmentPlan:id,patient_id,branch_id',
            'planItem:id,treatment_plan_id,name,tooth_number,quantity,price,final_amount',
        ]);

        $patientId = $session->treatmentPlan?->patient_id;
        if (! $patientId) {
            return null;
        }

        $progressAt = $this->resolveProgressDateTime($session);
        $progressDate = $progressAt->toDateString();
        $examSession = $this->resolveExamSession($session, $patientId, $progressDate);

        if (! $session->exam_session_id || (int) $session->exam_session_id !== (int) $examSession->id) {
            $session->exam_session_id = $examSession->id;
            $session->saveQuietly();
        }

        $status = $this->normalizeItemStatus((string) ($session->status ?? 'scheduled'));
        $day = TreatmentProgressDay::query()
            ->where('patient_id', (int) $patientId)
            ->where('exam_session_id', (int) $examSession->id)
            ->whereDate('progress_date', $progressDate)
            ->first();

        if (! $day) {
            $day = TreatmentProgressDay::query()->create([
                'patient_id' => (int) $patientId,
                'exam_session_id' => (int) $examSession->id,
                'progress_date' => $progressDate,
                'treatment_plan_id' => $session->treatment_plan_id ? (int) $session->treatment_plan_id : null,
                'branch_id' => $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
                'status' => TreatmentProgressDay::STATUS_PLANNED,
                'created_by' => $session->created_by,
                'updated_by' => $session->updated_by,
            ]);
        }

        if ($day->status !== TreatmentProgressDay::STATUS_LOCKED) {
            $day->fill([
                'treatment_plan_id' => $session->treatment_plan_id ? (int) $session->treatment_plan_id : null,
                'branch_id' => $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
                'updated_by' => $session->updated_by,
            ]);
            $day->save();
        }

        $quantity = max(1, (float) ($session->planItem?->quantity ?? 1));
        $unitPrice = (float) ($session->planItem?->price ?? 0);
        $totalAmount = (float) ($session->planItem?->final_amount ?? ($quantity * $unitPrice));

        $item = TreatmentProgressItem::query()->firstOrNew([
            'treatment_session_id' => $session->id,
        ]);

        $item->fill([
            'treatment_progress_day_id' => $day->id,
            'patient_id' => (int) $patientId,
            'exam_session_id' => (int) $examSession->id,
            'treatment_plan_id' => $session->treatment_plan_id ? (int) $session->treatment_plan_id : null,
            'plan_item_id' => $session->plan_item_id ? (int) $session->plan_item_id : null,
            'doctor_id' => $session->doctor_id ? (int) $session->doctor_id : null,
            'assistant_id' => $session->assistant_id ? (int) $session->assistant_id : null,
            'tooth_number' => $session->planItem?->tooth_number,
            'procedure_name' => $session->planItem?->name ?: $session->procedure,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'status' => $status,
            'performed_at' => $progressAt,
            'notes' => $session->notes,
            'created_by' => $session->created_by,
            'updated_by' => $session->updated_by,
        ]);
        $item->save();

        $this->refreshProgressDayStatus($day);
        $this->examSessionLifecycleService->refresh((int) $examSession->id);

        return $item;
    }

    public function deleteByTreatmentSession(TreatmentSession $session): void
    {
        $item = TreatmentProgressItem::query()
            ->where('treatment_session_id', $session->id)
            ->first();

        $examSessionId = $item?->exam_session_id ?: $session->exam_session_id;
        $day = $item?->progressDay;

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
            $existing = ExamSession::query()->find((int) $session->exam_session_id);
            if ($existing) {
                return $existing;
            }
        }

        $query = ExamSession::query()
            ->where('patient_id', $patientId)
            ->whereDate('session_date', $progressDate);

        if ($session->treatmentPlan?->branch_id) {
            $query->where('branch_id', (int) $session->treatmentPlan->branch_id);
        }

        $existing = $query->orderByDesc('id')->first();
        if ($existing) {
            return $existing;
        }

        $status = in_array((string) $session->status, ['done', 'completed'], true)
            ? ExamSession::STATUS_COMPLETED
            : ExamSession::STATUS_IN_PROGRESS;

        return ExamSession::query()->create([
            'patient_id' => $patientId,
            'visit_episode_id' => $this->resolveVisitEpisodeId(
                patientId: $patientId,
                branchId: $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
                progressDate: $progressDate,
            ),
            'branch_id' => $session->treatmentPlan?->branch_id ? (int) $session->treatmentPlan->branch_id : null,
            'doctor_id' => $session->doctor_id ? (int) $session->doctor_id : null,
            'session_date' => $progressDate,
            'status' => $status,
            'created_by' => $session->created_by,
            'updated_by' => $session->updated_by,
        ]);
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
