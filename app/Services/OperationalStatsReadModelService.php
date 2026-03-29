<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OperationalStatsReadModelService
{
    /**
     * @return array{
     *     new_customers_today:int,
     *     appointments_today:int,
     *     pending_confirmations:int
     * }
     */
    public function summary(?User $user = null, ?CarbonInterface $referenceTime = null): array
    {
        $now = $referenceTime ? now()->setTimestamp($referenceTime->getTimestamp()) : now();
        $startOfDay = $now->copy()->startOfDay();

        return [
            'new_customers_today' => $this->customerQuery($user)
                ->where('created_at', '>=', $startOfDay)
                ->count(),
            'appointments_today' => $this->appointmentQuery($user)
                ->whereDate('date', $startOfDay->toDateString())
                ->count(),
            'pending_confirmations' => $this->appointmentQuery($user)
                ->whereIn('status', Appointment::statusesForQuery([Appointment::STATUS_SCHEDULED]))
                ->where('date', '>=', $now)
                ->count(),
        ];
    }

    protected function customerQuery(?User $user): Builder
    {
        return BranchAccess::scopeQueryByUserAccessibleBranches(
            Customer::query(),
            $user,
            'branch_id',
        );
    }

    protected function appointmentQuery(?User $user): Builder
    {
        return BranchAccess::scopeQueryByUserAccessibleBranches(
            Appointment::query(),
            $user,
            'branch_id',
        );
    }
}
