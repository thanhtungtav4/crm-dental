<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\VisitEpisode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OperationalKpiService
{
    /**
     * @return array{
     *     metrics:array<string, float|int>,
     *     lineage:array{
     *         generated_at:string,
     *         branch_id:int|null,
     *         window:array{from:string,to:string},
     *         sources:array<int, array<string, mixed>>
     *     }
     * }
     */
    public function buildSnapshot(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $appointmentsQuery = Appointment::query()
            ->whereBetween('date', [$from, $to])
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));

        $totalBookings = (int) (clone $appointmentsQuery)->count();
        $attendedVisits = (int) (clone $appointmentsQuery)
            ->whereIn('status', Appointment::statusesForQuery([
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_IN_PROGRESS,
                Appointment::STATUS_COMPLETED,
            ]))
            ->count();
        $noShowCount = (int) (clone $appointmentsQuery)
            ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))
            ->count();

        $planItemsQuery = PlanItem::query()
            ->whereBetween('created_at', [$from, $to])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereHas('treatmentPlan', fn (Builder $innerQuery) => $innerQuery->where('branch_id', $branchId))
            );

        $approvalDenominator = (int) (clone $planItemsQuery)
            ->whereIn('approval_status', [
                PlanItem::APPROVAL_PROPOSED,
                PlanItem::APPROVAL_APPROVED,
                PlanItem::APPROVAL_DECLINED,
            ])
            ->count();
        $approvalAccepted = (int) (clone $planItemsQuery)
            ->where('approval_status', PlanItem::APPROVAL_APPROVED)
            ->count();

        $receiptsQuery = Payment::query()
            ->where('direction', 'receipt')
            ->whereBetween('paid_at', [$from, $to])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereHas('invoice.patient', fn (Builder $innerQuery) => $innerQuery->where('first_branch_id', $branchId))
            );

        $receiptsAmount = (float) (clone $receiptsQuery)->sum('amount');
        $payingPatients = (int) (clone $receiptsQuery)
            ->with('invoice:id,patient_id')
            ->get()
            ->pluck('invoice.patient_id')
            ->filter()
            ->unique()
            ->count();

        $episodesQuery = VisitEpisode::query()
            ->whereBetween('scheduled_at', [$from, $to])
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));

        $plannedChairMinutes = (float) ((clone $episodesQuery)->sum('planned_duration_minutes') ?: 0);
        $actualChairMinutes = (float) ((clone $episodesQuery)->sum('chair_minutes') ?: 0);

        $recallQuery = Note::query()
            ->where('care_type', 'recall_recare')
            ->whereBetween('care_at', [$from, $to])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereHas('patient', fn (Builder $innerQuery) => $innerQuery->where('first_branch_id', $branchId))
            );

        $recallTotal = (int) (clone $recallQuery)->count();
        $recallDone = (int) (clone $recallQuery)
            ->whereIn('care_status', Note::statusesForQuery([Note::CARE_STATUS_DONE]))
            ->count();

        $lifetimeReceiptsQuery = Payment::query()
            ->where('direction', 'receipt')
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereHas('invoice.patient', fn (Builder $innerQuery) => $innerQuery->where('first_branch_id', $branchId))
            );

        $lifetimeReceipts = (float) (clone $lifetimeReceiptsQuery)->sum('amount');
        $lifetimePatients = (int) (clone $lifetimeReceiptsQuery)
            ->with('invoice:id,patient_id')
            ->get()
            ->pluck('invoice.patient_id')
            ->filter()
            ->unique()
            ->count();

        $metrics = [
            'booking_count' => $totalBookings,
            'visit_count' => $attendedVisits,
            'booking_to_visit_rate' => $this->ratioPercent($attendedVisits, $totalBookings),
            'no_show_count' => $noShowCount,
            'no_show_rate' => $this->ratioPercent($noShowCount, $totalBookings),
            'treatment_acceptance_rate' => $this->ratioPercent($approvalAccepted, $approvalDenominator),
            'revenue_per_patient' => $payingPatients > 0 ? round($receiptsAmount / $payingPatients, 2) : 0.0,
            'chair_utilization_rate' => $this->ratioPercent($actualChairMinutes, $plannedChairMinutes),
            'recall_rate' => $this->ratioPercent($recallDone, $recallTotal),
            'ltv_patient' => $lifetimePatients > 0 ? round($lifetimeReceipts / $lifetimePatients, 2) : 0.0,
        ];

        return [
            'metrics' => $metrics,
            'lineage' => [
                'generated_at' => now()->toIso8601String(),
                'branch_id' => $branchId,
                'window' => [
                    'from' => $from->toDateTimeString(),
                    'to' => $to->toDateTimeString(),
                ],
                'sources' => [
                    ['table' => 'appointments', 'rows' => $totalBookings],
                    ['table' => 'plan_items', 'rows' => (int) (clone $planItemsQuery)->count()],
                    ['table' => 'payments', 'rows' => (int) (clone $receiptsQuery)->count()],
                    ['table' => 'visit_episodes', 'rows' => (int) (clone $episodesQuery)->count()],
                    ['table' => 'notes', 'rows' => $recallTotal],
                ],
            ],
        ];
    }

    protected function ratioPercent(float|int $numerator, float|int $denominator): float
    {
        if ((float) $denominator <= 0) {
            return 0.0;
        }

        return round(((float) $numerator / (float) $denominator) * 100, 2);
    }
}
