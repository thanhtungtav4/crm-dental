<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use App\Models\PlanItem;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;

class PatientRiskScoringService
{
    /**
     * @return array{
     *     model_version:string,
     *     no_show_risk_score:float,
     *     churn_risk_score:float,
     *     risk_level:string,
     *     recommended_action:string,
     *     feature_payload:array<string,mixed>
     * }
     */
    public function scorePatient(Patient $patient, Carbon $asOf): array
    {
        $windowDays = max(30, ClinicRuntimeSettings::riskNoShowWindowDays());
        $windowStart = $asOf->copy()->subDays($windowDays)->startOfDay();

        $appointmentsInWindow = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereBetween('date', [$windowStart, $asOf])
            ->count();

        $noShowCount = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereBetween('date', [$windowStart, $asOf])
            ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_NO_SHOW]))
            ->count();

        $noShowRate = $appointmentsInWindow > 0
            ? round(($noShowCount / $appointmentsInWindow) * 100, 2)
            : 0.0;

        $lastVisitAt = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('status', Appointment::statusesForQuery([
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_IN_PROGRESS,
            ]))
            ->max('date');

        $lastVisitDate = $lastVisitAt
            ? Carbon::parse($lastVisitAt)
            : ($patient->created_at ? Carbon::parse($patient->created_at) : $asOf->copy());

        $daysSinceLastVisit = max(0, $lastVisitDate->startOfDay()->diffInDays($asOf->copy()->startOfDay()));

        $openCareTickets = Note::query()
            ->where('patient_id', $patient->id)
            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
            ->count();

        $overdueBalance = (float) Invoice::query()
            ->where('patient_id', $patient->id)
            ->whereNotIn('status', [
                Invoice::STATUS_CANCELLED,
                Invoice::STATUS_PAID,
                Invoice::STATUS_DRAFT,
            ])
            ->where(function ($query) use ($asOf): void {
                $query->whereDate('due_date', '<=', $asOf->toDateString())
                    ->orWhere('status', Invoice::STATUS_OVERDUE);
            })
            ->get(['total_amount', 'paid_amount'])
            ->sum(function (Invoice $invoice): float {
                return max(0, round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2));
            });

        $pendingPlanItems = PlanItem::query()
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patient->id))
            ->whereIn('approval_status', [
                PlanItem::APPROVAL_DRAFT,
                PlanItem::APPROVAL_PROPOSED,
                PlanItem::APPROVAL_DECLINED,
            ])
            ->count();

        $noShowRiskScore = min(100, round(
            ($noShowRate * 0.55)
            + min(20, $noShowCount * 4)
            + ($openCareTickets > 0 ? 8 : 0)
            + ($daysSinceLastVisit > 30 ? 10 : 0),
            2
        ));

        $churnRiskScore = min(100, round(
            min(45, $daysSinceLastVisit / 2.5)
            + ($overdueBalance > 0 ? 20 : 0)
            + min(15, $openCareTickets * 4)
            + min(10, $pendingPlanItems * 2)
            + ($noShowRate * 0.2),
            2
        ));

        $maxRiskScore = max($noShowRiskScore, $churnRiskScore);
        $riskLevel = $this->resolveRiskLevel($maxRiskScore);

        return [
            'model_version' => PatientRiskProfile::MODEL_VERSION_BASELINE,
            'no_show_risk_score' => $noShowRiskScore,
            'churn_risk_score' => $churnRiskScore,
            'risk_level' => $riskLevel,
            'recommended_action' => $this->recommendAction(
                riskLevel: $riskLevel,
                noShowRate: $noShowRate,
                daysSinceLastVisit: $daysSinceLastVisit,
                overdueBalance: $overdueBalance,
            ),
            'feature_payload' => [
                'window_days' => $windowDays,
                'appointments_in_window' => $appointmentsInWindow,
                'no_show_count' => $noShowCount,
                'no_show_rate' => $noShowRate,
                'days_since_last_visit' => $daysSinceLastVisit,
                'open_care_tickets' => $openCareTickets,
                'overdue_balance' => $overdueBalance,
                'pending_plan_items' => $pendingPlanItems,
            ],
        ];
    }

    /**
     * @param  array{
     *     model_version:string,
     *     no_show_risk_score:float,
     *     churn_risk_score:float,
     *     risk_level:string,
     *     recommended_action:string,
     *     feature_payload:array<string,mixed>
     * }  $risk
     */
    public function upsertProfile(Patient $patient, Carbon $asOf, array $risk, ?int $actorId = null): PatientRiskProfile
    {
        $profile = PatientRiskProfile::query()
            ->where('patient_id', $patient->id)
            ->where('model_version', $risk['model_version'])
            ->whereDate('as_of_date', $asOf->toDateString())
            ->first();

        if (! $profile) {
            $profile = new PatientRiskProfile([
                'patient_id' => $patient->id,
                'as_of_date' => $asOf->toDateString(),
                'model_version' => $risk['model_version'],
            ]);
        }

        $profile->fill([
            'no_show_risk_score' => $risk['no_show_risk_score'],
            'churn_risk_score' => $risk['churn_risk_score'],
            'risk_level' => $risk['risk_level'],
            'recommended_action' => $risk['recommended_action'],
            'generated_at' => now(),
            'created_by' => $actorId,
            'feature_payload' => $risk['feature_payload'],
        ]);

        $profile->save();

        return $profile;
    }

    protected function resolveRiskLevel(float $score): string
    {
        $highThreshold = ClinicRuntimeSettings::riskHighThreshold();
        $mediumThreshold = ClinicRuntimeSettings::riskMediumThreshold();

        if ($score >= $highThreshold) {
            return PatientRiskProfile::LEVEL_HIGH;
        }

        if ($score >= $mediumThreshold) {
            return PatientRiskProfile::LEVEL_MEDIUM;
        }

        return PatientRiskProfile::LEVEL_LOW;
    }

    protected function recommendAction(
        string $riskLevel,
        float $noShowRate,
        int $daysSinceLastVisit,
        float $overdueBalance,
    ): string {
        if ($riskLevel === PatientRiskProfile::LEVEL_HIGH) {
            if ($overdueBalance > 0) {
                return 'Ưu tiên gọi trong 24h để xử lý công nợ và hẹn tái khám.';
            }

            if ($noShowRate >= 30) {
                return 'Nhắc lịch đa kênh + xác nhận trước 24h cho các lịch hẹn tới.';
            }

            return 'CSKH chủ động gọi lại trong 24h, đề xuất gói tái khám phù hợp.';
        }

        if ($riskLevel === PatientRiskProfile::LEVEL_MEDIUM) {
            if ($daysSinceLastVisit >= 90) {
                return 'Đẩy chiến dịch reactivation trong 3 ngày và theo dõi phản hồi.';
            }

            return 'Nhắc lịch theo kịch bản chuẩn và theo dõi tỷ lệ xác nhận.';
        }

        return 'Theo dõi định kỳ, chưa cần can thiệp tăng cường.';
    }
}
