<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Payment;
use Database\Seeders\FinanceScenarioSeeder;
use Illuminate\Database\Eloquent\Builder;

class FinanceOperationalReadModelService
{
    /**
     * @param  array<int, int>  $branchIds
     * @return array{
     *     visible_branch_count:int,
     *     needs_overdue_sync_count:int,
     *     overdue_count:int,
     *     partial_count:int,
     *     dunning_candidate_count:int,
     *     reversible_receipt_count:int,
     *     tone:string,
     *     status:string,
     *     overdue_scenario_invoice:?Invoice,
     *     reversal_scenario_payment:?Payment,
     *     installment_scenario_plan:?InstallmentPlan
     * }
     */
    public function summary(array $branchIds): array
    {
        $needsOverdueSyncCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_PARTIAL,
            ])
            ->count();

        $overdueCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->where('status', Invoice::STATUS_OVERDUE)
            ->count();

        $partialCount = $this->applyBranchScope(
            Invoice::query(),
            $branchIds,
        )
            ->where('status', Invoice::STATUS_PARTIAL)
            ->count();

        $dunningCandidateCount = $this->applyBranchScope(
            InstallmentPlan::query(),
            $branchIds,
        )
            ->whereIn('status', [
                InstallmentPlan::STATUS_ACTIVE,
                InstallmentPlan::STATUS_DEFAULTED,
            ])
            ->whereDate('next_due_date', '<', today())
            ->count();

        $reversibleReceiptCount = $this->applyBranchScope(
            Payment::query(),
            $branchIds,
        )
            ->where('direction', 'receipt')
            ->whereNull('reversal_of_id')
            ->whereNull('reversed_at')
            ->count();

        $tone = $needsOverdueSyncCount > 0
            ? 'danger'
            : (($dunningCandidateCount > 0 || $overdueCount > 0) ? 'warning' : 'success');

        $status = $needsOverdueSyncCount > 0
            ? 'Needs aging sync'
            : (($dunningCandidateCount > 0 || $overdueCount > 0) ? 'Collections backlog' : 'Healthy');

        return [
            'visible_branch_count' => count($branchIds),
            'needs_overdue_sync_count' => $needsOverdueSyncCount,
            'overdue_count' => $overdueCount,
            'partial_count' => $partialCount,
            'dunning_candidate_count' => $dunningCandidateCount,
            'reversible_receipt_count' => $reversibleReceiptCount,
            'tone' => $tone,
            'status' => $status,
            'overdue_scenario_invoice' => $this->applyBranchScope(
                Invoice::query()->with(['patient:id,full_name,patient_code', 'branch:id,name']),
                $branchIds,
            )
                ->where('invoice_no', FinanceScenarioSeeder::OVERDUE_INVOICE_NO)
                ->first(),
            'reversal_scenario_payment' => $this->applyBranchScope(
                Payment::query()->with(['invoice.patient:id,full_name,patient_code', 'branch:id,name']),
                $branchIds,
            )
                ->where('transaction_ref', FinanceScenarioSeeder::REVERSAL_RECEIPT_TRANSACTION_REF)
                ->first(),
            'installment_scenario_plan' => $this->applyBranchScope(
                InstallmentPlan::query()->with(['patient:id,full_name,patient_code', 'branch:id,name']),
                $branchIds,
            )
                ->where('plan_code', FinanceScenarioSeeder::INSTALLMENT_PLAN_CODE)
                ->first(),
        ];
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    protected function applyBranchScope(Builder $query, array $branchIds, string $column = 'branch_id'): Builder
    {
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }
}
