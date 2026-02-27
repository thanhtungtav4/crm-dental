<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinanceBranchAttributionReconciliationService
{
    /**
     * @return array{
     *     window:array{from:string,to:string,branch_id:int|null},
     *     rows:array<int, array{
     *         branch_id:int|null,
     *         branch_name:string,
     *         invoice_current_amount:float,
     *         invoice_legacy_amount:float,
     *         invoice_delta_amount:float,
     *         invoice_current_count:int,
     *         invoice_legacy_count:int,
     *         receipt_current_amount:float,
     *         receipt_legacy_amount:float,
     *         receipt_delta_amount:float,
     *         receipt_current_count:int,
     *         receipt_legacy_count:int
     *     }>,
     *     summary:array{
     *         invoice_current_amount:float,
     *         invoice_legacy_amount:float,
     *         invoice_delta_amount:float,
     *         invoice_current_count:int,
     *         invoice_legacy_count:int,
     *         receipt_current_amount:float,
     *         receipt_legacy_amount:float,
     *         receipt_delta_amount:float,
     *         receipt_current_count:int,
     *         receipt_legacy_count:int,
     *         invoice_mismatch_count:int,
     *         receipt_mismatch_count:int
     *     }
     * }
     */
    public function build(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $invoiceCurrent = $this->loadInvoiceCurrentByBranch($from, $to, $branchId);
        $invoiceLegacy = $this->loadInvoiceLegacyByBranch($from, $to, $branchId);
        $receiptCurrent = $this->loadReceiptCurrentByBranch($from, $to, $branchId);
        $receiptLegacy = $this->loadReceiptLegacyByBranch($from, $to, $branchId);

        $branchKeys = collect(array_keys($invoiceCurrent))
            ->merge(array_keys($invoiceLegacy))
            ->merge(array_keys($receiptCurrent))
            ->merge(array_keys($receiptLegacy))
            ->unique()
            ->values();

        $branchIds = $branchKeys
            ->map(fn (string $key): ?int => $key === 'unassigned' ? null : (int) $key)
            ->filter(fn (?int $value): bool => $value !== null)
            ->map(fn (?int $value): int => (int) $value)
            ->values()
            ->all();

        $branchNameMap = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $rows = $branchKeys
            ->map(function (string $key) use ($invoiceCurrent, $invoiceLegacy, $receiptCurrent, $receiptLegacy, $branchNameMap): array {
                $invoiceCurrentRow = $invoiceCurrent[$key] ?? ['amount' => 0.0, 'count' => 0];
                $invoiceLegacyRow = $invoiceLegacy[$key] ?? ['amount' => 0.0, 'count' => 0];
                $receiptCurrentRow = $receiptCurrent[$key] ?? ['amount' => 0.0, 'count' => 0];
                $receiptLegacyRow = $receiptLegacy[$key] ?? ['amount' => 0.0, 'count' => 0];

                $branchId = $key === 'unassigned' ? null : (int) $key;

                return [
                    'branch_id' => $branchId,
                    'branch_name' => $branchId !== null
                        ? (string) ($branchNameMap->get($branchId) ?? ('Chi nhánh #'.$branchId))
                        : 'Unassigned',
                    'invoice_current_amount' => (float) $invoiceCurrentRow['amount'],
                    'invoice_legacy_amount' => (float) $invoiceLegacyRow['amount'],
                    'invoice_delta_amount' => round((float) $invoiceCurrentRow['amount'] - (float) $invoiceLegacyRow['amount'], 2),
                    'invoice_current_count' => (int) $invoiceCurrentRow['count'],
                    'invoice_legacy_count' => (int) $invoiceLegacyRow['count'],
                    'receipt_current_amount' => (float) $receiptCurrentRow['amount'],
                    'receipt_legacy_amount' => (float) $receiptLegacyRow['amount'],
                    'receipt_delta_amount' => round((float) $receiptCurrentRow['amount'] - (float) $receiptLegacyRow['amount'], 2),
                    'receipt_current_count' => (int) $receiptCurrentRow['count'],
                    'receipt_legacy_count' => (int) $receiptLegacyRow['count'],
                ];
            })
            ->sortBy(fn (array $row): string => $row['branch_name'])
            ->values()
            ->all();

        $summary = [
            'invoice_current_amount' => round(collect($rows)->sum('invoice_current_amount'), 2),
            'invoice_legacy_amount' => round(collect($rows)->sum('invoice_legacy_amount'), 2),
            'invoice_delta_amount' => round(collect($rows)->sum('invoice_delta_amount'), 2),
            'invoice_current_count' => (int) collect($rows)->sum('invoice_current_count'),
            'invoice_legacy_count' => (int) collect($rows)->sum('invoice_legacy_count'),
            'receipt_current_amount' => round(collect($rows)->sum('receipt_current_amount'), 2),
            'receipt_legacy_amount' => round(collect($rows)->sum('receipt_legacy_amount'), 2),
            'receipt_delta_amount' => round(collect($rows)->sum('receipt_delta_amount'), 2),
            'receipt_current_count' => (int) collect($rows)->sum('receipt_current_count'),
            'receipt_legacy_count' => (int) collect($rows)->sum('receipt_legacy_count'),
            'invoice_mismatch_count' => $this->countInvoiceBranchMismatches($from, $to, $branchId),
            'receipt_mismatch_count' => $this->countReceiptBranchMismatches($from, $to, $branchId),
        ];

        return [
            'window' => [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'branch_id' => $branchId,
            ],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, array{amount:float,count:int}>
     */
    protected function loadInvoiceCurrentByBranch(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return Invoice::query()
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->whereRaw('COALESCE(invoices.issued_at, invoices.created_at) BETWEEN ? AND ?', [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereRaw('COALESCE(invoices.branch_id, patients.first_branch_id) = ?', [$branchId]),
            )
            ->selectRaw('COALESCE(invoices.branch_id, patients.first_branch_id) AS branch_id')
            ->selectRaw('COUNT(invoices.id) AS row_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) AS total_amount')
            ->groupByRaw('COALESCE(invoices.branch_id, patients.first_branch_id)')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->branchAggregateKey($row->branch_id) => [
                    'amount' => (float) ($row->total_amount ?? 0),
                    'count' => (int) ($row->row_count ?? 0),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{amount:float,count:int}>
     */
    protected function loadInvoiceLegacyByBranch(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return Invoice::query()
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->whereRaw('COALESCE(invoices.issued_at, invoices.created_at) BETWEEN ? AND ?', [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->where('patients.first_branch_id', $branchId),
            )
            ->selectRaw('patients.first_branch_id AS branch_id')
            ->selectRaw('COUNT(invoices.id) AS row_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) AS total_amount')
            ->groupBy('patients.first_branch_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->branchAggregateKey($row->branch_id) => [
                    'amount' => (float) ($row->total_amount ?? 0),
                    'count' => (int) ($row->row_count ?? 0),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{amount:float,count:int}>
     */
    protected function loadReceiptCurrentByBranch(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return Payment::query()
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->where('payments.direction', 'receipt')
            ->whereBetween('payments.paid_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereRaw('COALESCE(payments.branch_id, invoices.branch_id, patients.first_branch_id) = ?', [$branchId]),
            )
            ->selectRaw('COALESCE(payments.branch_id, invoices.branch_id, patients.first_branch_id) AS branch_id')
            ->selectRaw('COUNT(payments.id) AS row_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) AS total_amount')
            ->groupByRaw('COALESCE(payments.branch_id, invoices.branch_id, patients.first_branch_id)')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->branchAggregateKey($row->branch_id) => [
                    'amount' => (float) ($row->total_amount ?? 0),
                    'count' => (int) ($row->row_count ?? 0),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{amount:float,count:int}>
     */
    protected function loadReceiptLegacyByBranch(Carbon $from, Carbon $to, ?int $branchId): array
    {
        return Payment::query()
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->where('payments.direction', 'receipt')
            ->whereBetween('payments.paid_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->where('patients.first_branch_id', $branchId),
            )
            ->selectRaw('patients.first_branch_id AS branch_id')
            ->selectRaw('COUNT(payments.id) AS row_count')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) AS total_amount')
            ->groupBy('patients.first_branch_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->branchAggregateKey($row->branch_id) => [
                    'amount' => (float) ($row->total_amount ?? 0),
                    'count' => (int) ($row->row_count ?? 0),
                ],
            ])
            ->all();
    }

    protected function countInvoiceBranchMismatches(Carbon $from, Carbon $to, ?int $branchId): int
    {
        return Invoice::query()
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->whereNotNull('patients.first_branch_id')
            ->whereNotNull('invoices.branch_id')
            ->whereColumn('invoices.branch_id', '!=', 'patients.first_branch_id')
            ->whereRaw('COALESCE(invoices.issued_at, invoices.created_at) BETWEEN ? AND ?', [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereRaw('COALESCE(invoices.branch_id, patients.first_branch_id) = ?', [$branchId]),
            )
            ->count('invoices.id');
    }

    protected function countReceiptBranchMismatches(Carbon $from, Carbon $to, ?int $branchId): int
    {
        return Payment::query()
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('patients', 'patients.id', '=', 'invoices.patient_id')
            ->where('payments.direction', 'receipt')
            ->whereNotNull('patients.first_branch_id')
            ->whereRaw('COALESCE(payments.branch_id, invoices.branch_id, patients.first_branch_id) <> patients.first_branch_id')
            ->whereBetween('payments.paid_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->when(
                $branchId !== null,
                fn (Builder $query) => $query->whereRaw('COALESCE(payments.branch_id, invoices.branch_id, patients.first_branch_id) = ?', [$branchId]),
            )
            ->count('payments.id');
    }

    protected function branchAggregateKey(mixed $branchId): string
    {
        return $branchId === null ? 'unassigned' : (string) (int) $branchId;
    }
}
