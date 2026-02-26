<?php

namespace App\Console\Commands;

use App\Models\InstallmentPlan;
use Illuminate\Console\Command;

class SyncInstallmentPlanStatus extends Command
{
    protected $signature = 'installments:sync-status {--dry-run : Chỉ hiển thị thay đổi, không ghi DB}';

    protected $description = 'Đồng bộ trạng thái trả góp theo lịch và số tiền đã thu trên hóa đơn.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        InstallmentPlan::query()
            ->whereNotIn('status', [InstallmentPlan::STATUS_CANCELLED, InstallmentPlan::STATUS_COMPLETED])
            ->chunkById(200, function ($plans) use ($dryRun, &$updated): void {
                foreach ($plans as $plan) {
                    $before = [
                        'status' => $plan->status,
                        'remaining_amount' => (float) $plan->remaining_amount,
                        'next_due_date' => optional($plan->next_due_date)->toDateString(),
                    ];

                    $plan->syncFinancialState(persist: ! $dryRun);

                    $after = [
                        'status' => $plan->status,
                        'remaining_amount' => (float) $plan->remaining_amount,
                        'next_due_date' => optional($plan->next_due_date)->toDateString(),
                    ];

                    if ($before !== $after) {
                        $updated++;
                    }

                }
            });

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Installment plans synced. updated={$updated}");

        return self::SUCCESS;
    }
}
