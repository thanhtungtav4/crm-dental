<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use Carbon\Carbon;

class InstallmentPlanLifecycleService
{
    public function syncFinancialState(InstallmentPlan $plan, ?Carbon $asOf = null, bool $persist = true): void
    {
        if ($plan->status === InstallmentPlan::STATUS_CANCELLED) {
            return;
        }

        $asOfDate = ($asOf ?? now())->copy()->startOfDay();
        $paidAmount = (float) ($plan->invoice?->getTotalPaid() ?? 0);
        $paidTowardsInstallment = max(0, $paidAmount - (float) $plan->down_payment_amount);

        $schedule = $plan->schedule ?? [];
        $remainingPaid = $paidTowardsInstallment;
        $nextDueDate = null;
        $hasOverdue = false;

        foreach ($schedule as $index => $installment) {
            $installmentAmount = round(max(0, (float) ($installment['amount'] ?? 0)), 2);
            $paidForInstallment = min($installmentAmount, $remainingPaid);
            $remainingPaid = round(max(0, $remainingPaid - $paidForInstallment), 2);

            $dueDate = Carbon::parse((string) ($installment['due_date'] ?? $asOfDate->toDateString()))->startOfDay();
            $isPaid = $paidForInstallment >= $installmentAmount;
            $isOverdue = ! $isPaid && $dueDate->lt($asOfDate);

            if (! $isPaid && $nextDueDate === null) {
                $nextDueDate = $dueDate;
            }

            if ($isOverdue) {
                $hasOverdue = true;
            }

            $schedule[$index]['paid_amount'] = $paidForInstallment;
            $schedule[$index]['status'] = $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending');
            $schedule[$index]['paid_at'] = $isPaid ? ($schedule[$index]['paid_at'] ?? $asOfDate->toDateString()) : null;
        }

        $remainingAmount = round(max(0, (float) $plan->financed_amount - $paidTowardsInstallment), 2);

        $plan->schedule = $schedule;
        $plan->remaining_amount = $remainingAmount;
        $plan->next_due_date = $nextDueDate?->toDateString();

        if ($remainingAmount <= 0.0) {
            $plan->status = InstallmentPlan::STATUS_COMPLETED;
            $plan->dunning_level = 0;
        } elseif ($hasOverdue) {
            $plan->status = InstallmentPlan::STATUS_DEFAULTED;
        } else {
            $plan->status = InstallmentPlan::STATUS_ACTIVE;
            $plan->dunning_level = 0;
        }

        if ($persist) {
            $plan->save();
        }
    }
}
