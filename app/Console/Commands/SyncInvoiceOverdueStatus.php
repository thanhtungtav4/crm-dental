<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceWorkflowService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
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

        $actor = auth()->user();
        $updated = 0;
        $unchanged = 0;
        $dryRun = (bool) $this->option('dry-run');
        $canPersistAcrossBranches = $actor instanceof User
            && $actor->hasRole('AutomationService');

        $query = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereNotNull('due_date');

        if ($actor instanceof User && ! $actor->hasAnyRole(['Admin', 'AutomationService'])) {
            $branchIds = BranchAccess::accessibleBranchIds($actor);

            if ($branchIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('branch_id', $branchIds);
            }
        }

        $query
            ->chunkById(200, function ($invoices) use (&$updated, &$unchanged, $canPersistAcrossBranches, $dryRun): void {
                foreach ($invoices as $invoice) {
                    $oldStatus = $invoice->status;
                    $oldPaidAmount = round((float) $invoice->paid_amount, 2);
                    $oldPaidAt = $invoice->paid_at?->toDateTimeString();
                    $previewInvoice = clone $invoice;

                    $previewInvoice->paid_amount = $previewInvoice->getTotalPaid();
                    $previewInvoice->updatePaymentStatus();

                    $statusChanged = $previewInvoice->status !== $oldStatus;
                    $paidAmountChanged = round((float) $previewInvoice->paid_amount, 2) !== $oldPaidAmount;
                    $paidAtChanged = $previewInvoice->paid_at?->toDateTimeString() !== $oldPaidAt;

                    if (! $statusChanged && ! $paidAmountChanged && ! $paidAtChanged) {
                        $unchanged++;

                        continue;
                    }

                    $updated++;

                    if (! $dryRun) {
                        app(InvoiceWorkflowService::class)->syncFinancialStatus(
                            invoice: $invoice,
                            actorId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
                            auditAction: AuditLog::ACTION_SYNC,
                            metadata: [
                                'trigger' => 'automation_overdue_sync',
                                'command' => 'invoices:sync-overdue-status',
                            ],
                            persistQuietly: $canPersistAcrossBranches,
                        );
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
