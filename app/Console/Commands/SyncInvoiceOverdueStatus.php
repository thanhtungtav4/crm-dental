<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class SyncInvoiceOverdueStatus extends Command
{
    protected $signature = 'invoices:sync-overdue-status {--dry-run : Chỉ hiển thị thay đổi, không ghi DB}';

    protected $description = 'Đồng bộ trạng thái hóa đơn (đặc biệt overdue) theo due_date và công nợ.';

    public function handle(): int
    {
        $updated = 0;
        $unchanged = 0;
        $dryRun = (bool) $this->option('dry-run');

        Invoice::query()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereNotNull('due_date')
            ->chunkById(200, function ($invoices) use (&$updated, &$unchanged, $dryRun): void {
                foreach ($invoices as $invoice) {
                    $oldStatus = $invoice->status;
                    $oldPaidAt = $invoice->paid_at?->toDateTimeString();

                    $invoice->updatePaymentStatus();

                    $statusChanged = $invoice->status !== $oldStatus;
                    $paidAtChanged = $invoice->paid_at?->toDateTimeString() !== $oldPaidAt;

                    if (! $statusChanged && ! $paidAtChanged) {
                        $unchanged++;

                        continue;
                    }

                    $updated++;

                    if (! $dryRun) {
                        $invoice->save();
                    }
                }
            });

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Invoices synced. updated={$updated}, unchanged={$unchanged}");

        return self::SUCCESS;
    }
}

