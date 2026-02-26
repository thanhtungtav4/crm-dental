<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Console\Command;

class SyncInvoiceOverdueStatus extends Command
{
    protected $signature = 'invoices:sync-overdue-status {--dry-run : Chỉ hiển thị thay đổi, không ghi DB}';

    protected $description = 'Đồng bộ trạng thái hóa đơn (đặc biệt overdue) theo due_date và công nợ.';

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy automation đồng bộ công nợ hóa đơn.',
        );

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

        if (! $dryRun) {
            AuditLog::record(
                entityType: AuditLog::ENTITY_AUTOMATION,
                entityId: 0,
                action: AuditLog::ACTION_RUN,
                actorId: auth()->id(),
                metadata: [
                    'command' => 'invoices:sync-overdue-status',
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                ],
            );
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLY';
        $this->info("[{$mode}] Invoices synced. updated={$updated}, unchanged={$unchanged}");

        return self::SUCCESS;
    }
}
