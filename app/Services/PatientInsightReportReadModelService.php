<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use Illuminate\Database\Eloquent\Builder;

class PatientInsightReportReadModelService
{
    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function patientBreakdownQuery(?array $branchIds): Builder
    {
        return $this->applyDirectBranchScope(
            Patient::query()
                ->selectRaw('primary_doctor_id, count(*) as total_patients')
                ->with('primaryDoctor')
                ->groupBy('primary_doctor_id'),
            $branchIds,
            'first_branch_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_patients:int}
     */
    public function patientSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        $query = $this->applyDirectBranchScope(Patient::query(), $branchIds, 'first_branch_id');

        if ($branchIds === []) {
            return ['total_patients' => 0];
        }

        $this->applyDateRange($query, 'created_at', $from, $until);

        return [
            'total_patients' => (int) $query->count(),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function riskProfileQuery(?array $branchIds): Builder
    {
        return $this->applyRelatedBranchScope(
            PatientRiskProfile::query()->with(['patient.branch']),
            $branchIds,
            'patient',
            'first_branch_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{
     *     total:int,
     *     high:int,
     *     medium:int,
     *     low:int,
     *     average_no_show:float,
     *     average_churn:float,
     *     active_intervention_tickets:int
     * }
     */
    public function riskSummary(?array $branchIds, ?string $from, ?string $until, ?string $riskLevel = null): array
    {
        $query = $this->riskProfileQuery($branchIds);

        if ($branchIds === []) {
            return [
                'total' => 0,
                'high' => 0,
                'medium' => 0,
                'low' => 0,
                'average_no_show' => 0.0,
                'average_churn' => 0.0,
                'active_intervention_tickets' => 0,
            ];
        }

        $this->applyDateRange($query, 'as_of_date', $from, $until);

        if (filled($riskLevel)) {
            $query->where('risk_level', $riskLevel);
        }

        $activeInterventionTickets = Note::query()
            ->where('care_type', 'risk_high_follow_up')
            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()));

        if ($branchIds !== null) {
            $activeInterventionTickets->whereHas(
                'patient',
                fn (Builder $patientQuery): Builder => $patientQuery->whereIn('first_branch_id', $branchIds)
            );
        }

        return [
            'total' => (int) (clone $query)->count(),
            'high' => (int) (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_HIGH)->count(),
            'medium' => (int) (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_MEDIUM)->count(),
            'low' => (int) (clone $query)->where('risk_level', PatientRiskProfile::LEVEL_LOW)->count(),
            'average_no_show' => round((float) ((clone $query)->avg('no_show_risk_score') ?? 0), 2),
            'average_churn' => round((float) ((clone $query)->avg('churn_risk_score') ?? 0), 2),
            'active_intervention_tickets' => (int) $activeInterventionTickets->count(),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array{label:string, value:string}>
     */
    public function riskSummaryStatsPayload(
        ?array $branchIds,
        ?string $from,
        ?string $until,
        ?string $riskLevel = null,
    ): array {
        return PatientRiskProfile::summaryStatsPayload(
            $this->riskSummary($branchIds, $from, $until, $riskLevel)
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyDirectBranchScope(Builder $query, ?array $branchIds, string $column): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyRelatedBranchScope(Builder $query, ?array $branchIds, string $relation, string $column): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            $relation,
            fn (Builder $relationQuery): Builder => $relationQuery->whereIn($column, $branchIds)
        );
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
