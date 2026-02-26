<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\InstallmentPlan;
use App\Models\Note;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunInstallmentDunning extends Command
{
    protected $signature = 'installments:run-dunning {--date= : Ngày chạy (Y-m-d)} {--dry-run : Chỉ hiển thị thay đổi, không ghi DB}';

    protected $description = 'Chạy nhắc nợ trả góp theo aging bucket và log ticket CSKH.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation dunning trả góp.',
        );

        $dryRun = (bool) $this->option('dry-run');
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $queued = 0;

        InstallmentPlan::query()
            ->whereNotIn('status', [InstallmentPlan::STATUS_CANCELLED, InstallmentPlan::STATUS_COMPLETED])
            ->chunkById(100, function ($plans) use (&$queued, $asOf, $dryRun): void {
                foreach ($plans as $plan) {
                    $plan->syncFinancialState($asOf, ! $dryRun);

                    if (! $plan->shouldRunDunning($asOf)) {
                        continue;
                    }

                    $bucket = $plan->getCurrentAgingBucket($asOf);
                    $message = match ($bucket) {
                        1 => 'Nhắc nợ kỳ trả góp: quá hạn 1-3 ngày.',
                        2 => 'Nhắc nợ kỳ trả góp: quá hạn 4-7 ngày.',
                        default => 'Nhắc nợ kỳ trả góp: quá hạn trên 7 ngày, cần ưu tiên xử lý.',
                    };

                    if (! $dryRun) {
                        Note::query()->updateOrCreate(
                            [
                                'source_type' => InstallmentPlan::class,
                                'source_id' => $plan->id,
                                'care_type' => 'payment_reminder',
                            ],
                            [
                                'patient_id' => $plan->patient_id,
                                'customer_id' => $plan->patient?->customer_id,
                                'user_id' => auth()->id(),
                                'type' => Note::TYPE_GENERAL,
                                'care_channel' => 'call',
                                'care_status' => Note::CARE_STATUS_NOT_STARTED,
                                'care_mode' => 'scheduled',
                                'care_at' => now(),
                                'content' => $message,
                            ],
                        );

                        $plan->dunning_level = $bucket;
                        $plan->last_dunned_at = now();
                        $plan->save();
                    }

                    $queued++;
                }
            });

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'installments:run-dunning',
                    'queued' => $queued,
                    'as_of' => $asOf->toDateString(),
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Dunning processed. queued={$queued}");

        return self::SUCCESS;
    }
}
