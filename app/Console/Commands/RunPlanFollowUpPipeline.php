<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Note;
use App\Models\PlanItem;
use App\Models\TreatmentPlan;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\ClinicRuntimeSettings;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunPlanFollowUpPipeline extends Command
{
    protected $signature = 'care:run-plan-follow-up {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Chạy pipeline follow-up cho plan item chưa chốt (chưa duyệt/từ chối).';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation follow-up kế hoạch điều trị.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();

        $delayDays = ClinicRuntimeSettings::planFollowUpDelayDays();
        $followUpCutoff = $asOf->copy()->subDays($delayDays);

        $upserted = 0;

        PlanItem::query()
            ->with(['treatmentPlan.patient'])
            ->whereIn('approval_status', [
                PlanItem::APPROVAL_DRAFT,
                PlanItem::APPROVAL_PROPOSED,
                PlanItem::APPROVAL_DECLINED,
            ])
            ->where('created_at', '<=', $followUpCutoff)
            ->whereHas('treatmentPlan', function ($query): void {
                $query->whereNotIn('status', [
                    TreatmentPlan::STATUS_CANCELLED,
                    TreatmentPlan::STATUS_COMPLETED,
                ]);
            })
            ->chunkById(200, function ($items) use (&$upserted, $dryRun): void {
                foreach ($items as $item) {
                    $patientId = $item->treatmentPlan?->patient_id;

                    if (! $patientId) {
                        continue;
                    }

                    $content = $item->approval_status === PlanItem::APPROVAL_DECLINED
                        ? trim((string) ($item->approval_decline_reason ?: 'Bệnh nhân từ chối kế hoạch điều trị, cần gọi tư vấn lại.'))
                        : 'Kế hoạch điều trị chưa chốt. Cần follow-up tư vấn xác nhận điều trị.';

                    if (! $dryRun) {
                        Note::query()->updateOrCreate(
                            [
                                'source_type' => PlanItem::class,
                                'source_id' => $item->id,
                                'care_type' => 'treatment_plan_follow_up',
                            ],
                            [
                                'patient_id' => $patientId,
                                'customer_id' => $item->treatmentPlan?->patient?->customer_id,
                                'user_id' => $item->treatmentPlan?->doctor_id ?? $item->treatmentPlan?->created_by,
                                'type' => Note::TYPE_GENERAL,
                                'care_channel' => ClinicRuntimeSettings::defaultCareChannel(),
                                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                                'care_mode' => 'scheduled',
                                'is_recurring' => false,
                                'care_at' => now(),
                                'content' => $content,
                            ]
                        );
                    }

                    $upserted++;
                }
            });

        $resolved = 0;

        if (! $dryRun) {
            $resolved = Note::query()
                ->where('source_type', PlanItem::class)
                ->where('care_type', 'treatment_plan_follow_up')
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('plan_items')
                        ->join('treatment_plans', 'treatment_plans.id', '=', 'plan_items.treatment_plan_id')
                        ->whereColumn('plan_items.id', 'notes.source_id')
                        ->where(function ($innerQuery): void {
                            $innerQuery
                                ->where('plan_items.approval_status', PlanItem::APPROVAL_APPROVED)
                                ->orWhereIn('treatment_plans.status', [
                                    TreatmentPlan::STATUS_CANCELLED,
                                    TreatmentPlan::STATUS_COMPLETED,
                                ]);
                        });
                })
                ->update([
                    'care_status' => Note::CARE_STATUS_FAILED,
                ]);

            $resolved += Note::query()
                ->where('source_type', PlanItem::class)
                ->where('care_type', 'treatment_plan_follow_up')
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('plan_items')
                        ->whereColumn('plan_items.id', 'notes.source_id');
                })
                ->update([
                    'care_status' => Note::CARE_STATUS_FAILED,
                ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'care:run-plan-follow-up',
                    'upserted' => $upserted,
                    'resolved' => $resolved,
                    'cutoff' => $followUpCutoff->toDateString(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Plan follow-up processed. upserted={$upserted}, resolved={$resolved}, cutoff={$followUpCutoff->toDateString()}");

        return self::SUCCESS;
    }
}
