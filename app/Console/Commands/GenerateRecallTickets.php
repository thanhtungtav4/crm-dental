<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Note;
use App\Models\PlanItem;
use App\Services\RecallRuleEngineService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecallTickets extends Command
{
    protected $signature = 'care:generate-recall-tickets {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ preview, không ghi DB}';

    protected $description = 'Tạo ticket recall/re-care theo thủ thuật đã hoàn tất.';

    public function __construct(protected RecallRuleEngineService $ruleEngine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation recall/re-care.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->endOfDay()
            : now()->endOfDay();

        $createdOrUpdated = 0;
        $cancelled = 0;
        $completedIds = [];

        PlanItem::query()
            ->with(['treatmentPlan.patient', 'service'])
            ->where('status', PlanItem::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $asOf)
            ->chunkById(200, function ($items) use (&$createdOrUpdated, &$completedIds, $dryRun): void {
                foreach ($items as $item) {
                    $patientId = $item->treatmentPlan?->patient_id;

                    if (! $patientId) {
                        continue;
                    }

                    $completedIds[] = $item->id;

                    $rule = $this->ruleEngine->resolve(
                        serviceId: $item->service_id ? (int) $item->service_id : null,
                        branchId: $item->treatmentPlan?->branch_id ? (int) $item->treatmentPlan?->branch_id : null,
                    );

                    $completedAt = Carbon::parse($item->completed_at);
                    $careAt = $completedAt
                        ->copy()
                        ->addDays((int) $rule['offset_days'])
                        ->setTime(9, 0);

                    if ($dryRun) {
                        $createdOrUpdated++;

                        continue;
                    }

                    Note::query()->updateOrCreate(
                        [
                            'source_type' => PlanItem::class,
                            'source_id' => $item->id,
                            'care_type' => 'recall_recare',
                        ],
                        [
                            'patient_id' => $patientId,
                            'customer_id' => $item->treatmentPlan?->patient?->customer_id,
                            'user_id' => $item->treatmentPlan?->doctor_id ?? $item->treatmentPlan?->created_by,
                            'type' => Note::TYPE_GENERAL,
                            'care_channel' => (string) $rule['care_channel'],
                            'care_status' => Note::CARE_STATUS_NOT_STARTED,
                            'care_mode' => 'scheduled',
                            'is_recurring' => true,
                            'care_at' => $careAt,
                            'content' => sprintf(
                                'Recall/Re-care cho thủ thuật %s sau %d ngày.',
                                $item->service?->name ?? $item->name,
                                (int) $rule['offset_days'],
                            ),
                        ],
                    );

                    $createdOrUpdated++;
                }
            });

        if (! $dryRun) {
            $cancelled = Note::query()
                ->where('source_type', PlanItem::class)
                ->where('care_type', 'recall_recare')
                ->when(
                    $completedIds !== [],
                    fn ($query) => $query->whereNotIn('source_id', $completedIds),
                    fn ($query) => $query
                )
                ->whereIn('care_status', Note::statusesForQuery(Note::activeCareStatuses()))
                ->update([
                    'care_status' => Note::CARE_STATUS_FAILED,
                ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'care:generate-recall-tickets',
                    'upserted' => $createdOrUpdated,
                    'cancelled' => $cancelled,
                    'as_of' => $asOf->toDateString(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Recall tickets processed. upserted={$createdOrUpdated}, cancelled={$cancelled}, as_of={$asOf->toDateString()}");

        return self::SUCCESS;
    }
}
