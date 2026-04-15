<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\ReceiptExpense;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportReadModelService
{
    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function cashflowBreakdownQuery(?array $branchIds): Builder
    {
        return $this->applyBranchScope(
            ReceiptExpense::query()
                ->selectRaw(
                    'payment_method, sum(case when voucher_type = ? then amount else 0 end) as total_expense, sum(case when voucher_type = ? then 0 else amount end) as total_receipt',
                    ['expense', 'expense']
                )
                ->groupBy('payment_method'),
            $branchIds,
            'clinic_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_receipt:float,total_expense:float}
     */
    public function cashflowSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        $query = $this->applyBranchScope(ReceiptExpense::query(), $branchIds, 'clinic_id');

        if ($branchIds === []) {
            return [
                'total_receipt' => 0.0,
                'total_expense' => 0.0,
            ];
        }

        $this->applyDateRange($query, 'voucher_date', $from, $until);

        return [
            'total_receipt' => (float) (clone $query)
                ->where('voucher_type', '!=', 'expense')
                ->sum('amount'),
            'total_expense' => (float) (clone $query)
                ->where('voucher_type', 'expense')
                ->sum('amount'),
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array{label:string, value:string}>
     */
    public function cashflowStatsPayload(?array $branchIds, ?string $from, ?string $until): array
    {
        $summary = $this->cashflowSummary($branchIds, $from, $until);
        $totalReceipt = $summary['total_receipt'];
        $totalExpense = $summary['total_expense'];

        return [
            ['label' => 'Tổng thu', 'value' => number_format($totalReceipt).' đ'],
            ['label' => 'Tổng chi', 'value' => number_format($totalExpense).' đ'],
            ['label' => 'Biến động', 'value' => number_format($totalReceipt - $totalExpense).' đ'],
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    public function invoiceBalanceQuery(?array $branchIds): Builder
    {
        return $this->applyBranchScope(
            Invoice::query()->with('patient'),
            $branchIds,
            'branch_id',
        );
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array{total_amount:float,paid_amount:float,balance:float}
     */
    public function invoiceBalanceSummary(?array $branchIds, ?string $from, ?string $until): array
    {
        $query = $this->applyBranchScope(Invoice::query(), $branchIds, 'branch_id');

        if ($branchIds === []) {
            return [
                'total_amount' => 0.0,
                'paid_amount' => 0.0,
                'balance' => 0.0,
            ];
        }

        $this->applyDateRange($query, 'issued_at', $from, $until);

        $totalAmount = (float) (clone $query)->sum('total_amount');
        $paidAmount = (float) (clone $query)->sum('paid_amount');

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'balance' => $totalAmount - $paidAmount,
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     * @return array<int, array{label:string, value:string}>
     */
    public function invoiceBalanceStatsPayload(?array $branchIds, ?string $from, ?string $until): array
    {
        $summary = $this->invoiceBalanceSummary($branchIds, $from, $until);

        return [
            ['label' => 'Tổng phải thanh toán', 'value' => number_format($summary['total_amount']).' đ'],
            ['label' => 'Đã thanh toán', 'value' => number_format($summary['paid_amount']).' đ'],
            ['label' => 'Công nợ', 'value' => number_format($summary['balance']).' đ'],
        ];
    }

    /**
     * @param  array<int, int>|null  $branchIds
     */
    protected function applyBranchScope(Builder $query, ?array $branchIds, string $column): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $until): Builder
    {
        if (filled($from)) {
            $query->whereDate($column, '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate($column, '<=', $until);
        }

        return $query;
    }
}
