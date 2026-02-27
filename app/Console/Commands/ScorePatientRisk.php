<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientRiskProfile;
use App\Services\PatientRiskScoringService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScorePatientRisk extends Command
{
    protected $signature = 'patients:score-risk {--date= : Ngày chạy (Y-m-d)} {--patient_id= : Chỉ score 1 bệnh nhân} {--branch_id= : Chỉ score theo chi nhánh} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Tính risk profile no-show/churn baseline và tạo ticket can thiệp cho nhóm nguy cơ cao.';

    public function __construct(
        protected PatientRiskScoringService $riskScoringService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation risk scoring.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $persist = ! $dryRun;
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();
        $patientId = $this->option('patient_id');
        $branchId = $this->option('branch_id');
        $autoCreateHighRiskTicket = ClinicRuntimeSettings::riskAutoCreateHighRiskTicket();

        if (filled($branchId) && ! Branch::query()->whereKey((int) $branchId)->exists()) {
            $this->error('Chi nhánh không tồn tại.');

            return self::FAILURE;
        }

        $scored = 0;
        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;
        $ticketsUpserted = 0;
        $ticketsResolved = 0;

        $query = Patient::query()->with(['customer']);

        if (filled($patientId)) {
            $query->whereKey((int) $patientId);
        }

        if (filled($branchId)) {
            $query->where('first_branch_id', (int) $branchId);
        }

        $query
            ->orderBy('id')
            ->chunkById(200, function ($patients) use (
                $asOf,
                $persist,
                $autoCreateHighRiskTicket,
                &$scored,
                &$highRisk,
                &$mediumRisk,
                &$lowRisk,
                &$ticketsUpserted,
                &$ticketsResolved,
            ): void {
                foreach ($patients as $patient) {
                    $risk = $this->riskScoringService->scorePatient($patient, $asOf);
                    $scored++;

                    if ($risk['risk_level'] === PatientRiskProfile::LEVEL_HIGH) {
                        $highRisk++;
                    } elseif ($risk['risk_level'] === PatientRiskProfile::LEVEL_MEDIUM) {
                        $mediumRisk++;
                    } else {
                        $lowRisk++;
                    }

                    if (! $persist) {
                        continue;
                    }

                    $profile = $this->riskScoringService->upsertProfile(
                        patient: $patient,
                        asOf: $asOf,
                        risk: $risk,
                        actorId: auth()->id(),
                    );

                    if ($autoCreateHighRiskTicket && $profile->risk_level === PatientRiskProfile::LEVEL_HIGH) {
                        Note::query()->updateOrCreate(
                            [
                                'source_type' => PatientRiskProfile::class,
                                'source_id' => $profile->id,
                                'care_type' => 'risk_high_follow_up',
                            ],
                            [
                                'patient_id' => $patient->id,
                                'customer_id' => $patient->customer_id,
                                'user_id' => $patient->owner_staff_id ?? $patient->primary_doctor_id,
                                'type' => Note::TYPE_GENERAL,
                                'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                                'care_mode' => 'scheduled',
                                'is_recurring' => false,
                                'care_at' => now(),
                                'content' => "Risk cao ({$profile->no_show_risk_score}/{$profile->churn_risk_score}). {$profile->recommended_action}",
                            ],
                        );

                        $ticketsUpserted++;
                    }

                    if ($profile->risk_level !== PatientRiskProfile::LEVEL_HIGH) {
                        $ticketsResolved += Note::query()
                            ->where('patient_id', $patient->id)
                            ->where('care_type', 'risk_high_follow_up')
                            ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                            ->update([
                                'care_status' => Note::CARE_STATUS_FAILED,
                            ]);
                    }
                }
            });

        if ($persist) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'patients:score-risk',
                    'as_of' => $asOf->toDateString(),
                    'patient_id' => filled($patientId) ? (int) $patientId : null,
                    'branch_id' => filled($branchId) ? (int) $branchId : null,
                    'scored' => $scored,
                    'high_risk' => $highRisk,
                    'medium_risk' => $mediumRisk,
                    'low_risk' => $lowRisk,
                    'tickets_upserted' => $ticketsUpserted,
                    'tickets_resolved' => $ticketsResolved,
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info(
            "[{$mode}] Risk scored. ".
            "patients={$scored}, high={$highRisk}, medium={$mediumRisk}, low={$lowRisk}, ".
            "tickets_upserted={$ticketsUpserted}, tickets_resolved={$ticketsResolved}",
        );

        return self::SUCCESS;
    }
}
