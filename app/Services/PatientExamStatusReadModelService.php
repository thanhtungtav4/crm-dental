<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PlanItem;
use Illuminate\Support\Carbon;

class PatientExamStatusReadModelService
{
    /**
     * @return array<int, string>
     */
    public function treatmentProgressDates(Patient $patient): array
    {
        $progressDates = $patient->treatmentProgressDays()
            ->get(['progress_date'])
            ->map(fn ($day) => $day->progress_date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($progressDates !== []) {
            return $progressDates;
        }

        return $patient->treatmentSessions()
            ->get(['performed_at', 'start_at', 'end_at'])
            ->flatMap(function ($session) {
                return collect([
                    $session->performed_at,
                    $session->start_at,
                    $session->end_at,
                ])
                    ->filter()
                    ->map(fn ($dateTime) => Carbon::parse($dateTime)->toDateString());
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function toothTreatmentStates(Patient $patient): array
    {
        $statePriority = [
            'normal' => 0,
            'current' => 1,
            'completed' => 2,
            'in_treatment' => 3,
        ];

        $toothStates = [];

        $planItems = PlanItem::query()
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->get(['tooth_number', 'status']);

        foreach ($planItems as $planItem) {
            $targetState = $this->mapPlanItemStatusToToothState((string) $planItem->status);

            foreach ($planItem->getToothNumbers() as $toothNumber) {
                $toothKey = (string) $toothNumber;
                $currentState = $toothStates[$toothKey] ?? 'normal';

                if (($statePriority[$targetState] ?? 0) >= ($statePriority[$currentState] ?? 0)) {
                    $toothStates[$toothKey] = $targetState;
                }
            }
        }

        $sessionStates = $patient->treatmentSessions()
            ->get([
                'treatment_sessions.plan_item_id',
                'treatment_sessions.status',
            ]);

        $planItemsById = PlanItem::withTrashed()
            ->whereIn('id', $sessionStates->pluck('plan_item_id')->filter()->unique()->values())
            ->get(['id', 'tooth_number'])
            ->keyBy('id');

        foreach ($sessionStates as $sessionState) {
            $planItem = $planItemsById->get($sessionState->plan_item_id);

            if (! $planItem) {
                continue;
            }

            $targetState = $this->mapTreatmentSessionStatusToToothState((string) $sessionState->status);

            foreach ($planItem->getToothNumbers() as $toothNumber) {
                $toothKey = (string) $toothNumber;
                $currentState = $toothStates[$toothKey] ?? 'normal';

                if (($statePriority[$targetState] ?? 0) >= ($statePriority[$currentState] ?? 0)) {
                    $toothStates[$toothKey] = $targetState;
                }
            }
        }

        return $toothStates;
    }

    protected function mapPlanItemStatusToToothState(string $status): string
    {
        return match ($status) {
            'in_progress' => 'in_treatment',
            'completed' => 'completed',
            default => 'current',
        };
    }

    protected function mapTreatmentSessionStatusToToothState(string $status): string
    {
        return match ($status) {
            'done' => 'completed',
            'scheduled', 'follow_up' => 'in_treatment',
            default => 'current',
        };
    }
}
