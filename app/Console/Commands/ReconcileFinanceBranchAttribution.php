<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\FinanceBranchAttributionReconciliationService;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class ReconcileFinanceBranchAttribution extends Command
{
    protected $signature = 'finance:reconcile-branch-attribution
        {--from= : Từ ngày (Y-m-d)}
        {--to= : Đến ngày (Y-m-d)}
        {--branch_id= : Chỉ chạy cho 1 chi nhánh}
        {--apply : Tự động backfill branch attribution cho invoice/payment bị thiếu}
        {--strict : Fail command neu con mismatch invoice/receipt sau doi soat}
        {--export= : Đường dẫn file JSON output}';

    protected $description = 'Đối soát branch attribution tài chính (invoice/payment) giữa mô hình mới và legacy.';

    public function __construct(protected FinanceBranchAttributionReconciliationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền chạy đối soát branch attribution tài chính.',
        );

        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'))->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'))->endOfDay()
            : now()->endOfDay();
        $branchId = $this->option('branch_id') !== null ? (int) $this->option('branch_id') : null;

        $beforeReport = $this->service->build($from, $to, $branchId);
        $report = $beforeReport;
        $backfillSummary = null;

        if ((bool) $this->option('apply')) {
            $this->warn('APPLY_MODE: running branch attribution backfill for invoices/payments with missing branch_id...');

            $backfillSummary = $this->service->backfillBranchAttribution($from, $to, $branchId);
            $report = $this->service->build($from, $to, $branchId);
        }

        $rows = (array) Arr::get($report, 'rows', []);
        $summary = (array) Arr::get($report, 'summary', []);

        $this->line('RECONCILIATION_WINDOW: '.$from->toDateTimeString().' -> '.$to->toDateTimeString());
        $this->line('RECONCILIATION_BRANCH_SCOPE: '.($branchId ?? 'ALL'));

        if ($backfillSummary !== null) {
            $beforeSummary = (array) Arr::get($beforeReport, 'summary', []);
            $this->line('BEFORE_DELTA_INVOICES: '.$this->formatAmount((float) ($beforeSummary['invoice_delta_amount'] ?? 0)));
            $this->line('BEFORE_DELTA_RECEIPTS: '.$this->formatAmount((float) ($beforeSummary['receipt_delta_amount'] ?? 0)));
            $this->line(sprintf(
                'BACKFILL_COUNTS: invoices updated=%d skipped=%d failed=%d | payments updated=%d skipped=%d failed=%d',
                (int) ($backfillSummary['invoice_updated'] ?? 0),
                (int) ($backfillSummary['invoice_skipped'] ?? 0),
                (int) ($backfillSummary['invoice_failed'] ?? 0),
                (int) ($backfillSummary['payment_updated'] ?? 0),
                (int) ($backfillSummary['payment_skipped'] ?? 0),
                (int) ($backfillSummary['payment_failed'] ?? 0),
            ));
        }

        $this->table(
            [
                'Branch ID',
                'Branch',
                'Inv Current',
                'Inv Legacy',
                'Inv Delta',
                'Rcpt Current',
                'Rcpt Legacy',
                'Rcpt Delta',
                'Rcpt C/L',
            ],
            collect($rows)->map(function (array $row): array {
                return [
                    $row['branch_id'] ?? '-',
                    $row['branch_name'] ?? 'Unassigned',
                    $this->formatAmount((float) ($row['invoice_current_amount'] ?? 0)),
                    $this->formatAmount((float) ($row['invoice_legacy_amount'] ?? 0)),
                    $this->formatAmount((float) ($row['invoice_delta_amount'] ?? 0)),
                    $this->formatAmount((float) ($row['receipt_current_amount'] ?? 0)),
                    $this->formatAmount((float) ($row['receipt_legacy_amount'] ?? 0)),
                    $this->formatAmount((float) ($row['receipt_delta_amount'] ?? 0)),
                    sprintf('%d/%d', (int) ($row['receipt_current_count'] ?? 0), (int) ($row['receipt_legacy_count'] ?? 0)),
                ];
            })->all(),
        );

        $this->line('TOTAL_CURRENT_INVOICES: '.$this->formatAmount((float) ($summary['invoice_current_amount'] ?? 0)));
        $this->line('TOTAL_LEGACY_INVOICES: '.$this->formatAmount((float) ($summary['invoice_legacy_amount'] ?? 0)));
        $this->line('TOTAL_DELTA_INVOICES: '.$this->formatAmount((float) ($summary['invoice_delta_amount'] ?? 0)));
        $this->line('TOTAL_CURRENT_RECEIPTS: '.$this->formatAmount((float) ($summary['receipt_current_amount'] ?? 0)));
        $this->line('TOTAL_LEGACY_RECEIPTS: '.$this->formatAmount((float) ($summary['receipt_legacy_amount'] ?? 0)));
        $this->line('TOTAL_DELTA_RECEIPTS: '.$this->formatAmount((float) ($summary['receipt_delta_amount'] ?? 0)));
        $this->line('MISMATCH_COUNTS: invoices='.(int) ($summary['invoice_mismatch_count'] ?? 0).', receipts='.(int) ($summary['receipt_mismatch_count'] ?? 0));
        $this->line('MISSING_ATTRIBUTION_COUNTS: invoices='.(int) ($summary['invoice_missing_branch_id_count'] ?? 0).', receipts='.(int) ($summary['receipt_missing_branch_id_count'] ?? 0));

        $invoiceMissingBranchIdCount = (int) ($summary['invoice_missing_branch_id_count'] ?? 0);
        $receiptMissingBranchIdCount = (int) ($summary['receipt_missing_branch_id_count'] ?? 0);
        $hasMissingAttribution = $invoiceMissingBranchIdCount > 0 || $receiptMissingBranchIdCount > 0;

        $exportPath = $this->option('export')
            ? (string) $this->option('export')
            : storage_path('app/reconciliation/finance-branch-attribution-'.$from->toDateString().'_'.$to->toDateString().'.json');
        $exportPayload = [
            'window' => $report['window'] ?? [],
            'rows' => $report['rows'] ?? [],
            'summary' => $report['summary'] ?? [],
            'before' => $beforeReport,
            'after' => $report,
            'applied' => $backfillSummary !== null,
            'apply_summary' => $backfillSummary,
        ];

        $this->writeJsonReport($exportPath, $exportPayload);

        AuditLog::record(
            entityType: AuditLog::ENTITY_AUTOMATION,
            entityId: 0,
            action: AuditLog::ACTION_RUN,
            actorId: auth()->id(),
            metadata: [
                'command' => 'finance:reconcile-branch-attribution',
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'branch_id' => $branchId,
                'export_path' => $exportPath,
                'applied' => $backfillSummary !== null,
                'before_summary' => Arr::get($beforeReport, 'summary'),
                'apply_summary' => $backfillSummary,
                'summary' => $summary,
            ],
        );

        $this->info('Reconciliation report exported: '.$exportPath);

        if ((bool) $this->option('strict') && $hasMissingAttribution) {
            $this->error(sprintf(
                'Strict mode: finance branch attribution chua duoc persist day du (invoices_missing=%d, receipts_missing=%d).',
                $invoiceMissingBranchIdCount,
                $receiptMissingBranchIdCount,
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function writeJsonReport(string $path, array $report): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        File::put(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
