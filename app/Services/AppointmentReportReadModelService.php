<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\VisitEpisode;
use Illuminate\Database\Eloquent\Builder;

class AppointmentReportReadModelService
{
    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function appointmentQuery(?array $branchIds): Builder
    {
        return $this->applyBranchScope(
            Appointment::query()->with(['patient', 'doctor', 'visitEpisode']),
            $branchIds,
            'branch_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{
     *     total:int,
     *     new:int,
     *     cancelled:int,
     *     completed:int,
     *     avg_waiting:float,
     *     avg_chair:float,
     *     avg_overrun:float
     * }
     */
    public function appointmentSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        $appointmentQuery = $this->applyBranchScope(Appointment::query(), $branchIds, 'branch_id');

        if ($branchIds === []) {
            return [
                'total' => 0,
                'new' => 0,
                'cancelled' => 0,
                'completed' => 0,
                'avg_waiting' => 0.0,
                'avg_chair' => 0.0,
                'avg_overrun' => 0.0,
            ];
        }

        $this->applyDateRange($appointmentQuery, 'date', $from, $until);

        $visitEpisodeQuery = $this->applyBranchScope(VisitEpisode::query(), $branchIds, 'branch_id')
            ->whereHas('appointment', function (Builder $query) use ($from, $until): void {
                $this->applyDateRange($query, 'date', $from, $until);
            });

        return [
            'total' => (int) (clone $appointmentQuery)->count(),
            'new' => (int) (clone $appointmentQuery)
                ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))
                ->count(),
            'cancelled' => (int) (clone $appointmentQuery)
                ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_CANCELLED]))
                ->count(),
            'completed' => (int) (clone $appointmentQuery)
                ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_COMPLETED]))
                ->count(),
            'avg_waiting' => round((float) ((clone $visitEpisodeQuery)->whereNotNull('waiting_minutes')->avg('waiting_minutes') ?? 0), 1),
            'avg_chair' => round((float) ((clone $visitEpisodeQuery)->whereNotNull('chair_minutes')->avg('chair_minutes') ?? 0), 1),
            'avg_overrun' => round((float) ((clone $visitEpisodeQuery)->whereNotNull('overrun_minutes')->avg('overrun_minutes') ?? 0), 1),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyBranchScope(Builder $query, ?array $branchIds, string $column): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $until): Builder
    {
        if (filled($from)) {
            $query->whereDate($column, '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }
}
