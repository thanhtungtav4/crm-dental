<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;

class AppointmentSearchService
{
    /**
     * @return array<int, string>
     */
    public function customerOptionsForSearch(string $search): array
    {
        $query = Customer::query();

        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');
        $this->applyCustomerSearch($query, $search);

        return $query
            ->orderBy('full_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Customer $customer): array => [$customer->id => $this->customerLabel($customer)])
            ->all();
    }

    public function customerOptionLabel(?int $customerId): ?string
    {
        if (! $customerId) {
            return null;
        }

        $query = Customer::query()
            ->whereKey($customerId);

        BranchAccess::scopeQueryByAccessibleBranches($query, 'branch_id');

        $customer = $query->first();

        if (! $customer) {
            return null;
        }

        return $this->customerLabel($customer);
    }

    public function applyAppointmentParticipantSearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $appointmentQuery) use ($search): void {
            $appointmentQuery
                ->whereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $this->applyCustomerSearch(
                        query: $customerQuery,
                        search: $search,
                    );
                })
                ->orWhereHas('patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('patient_code', 'like', "%{$search}%")
                        ->orWhere(fn (Builder $phoneQuery): Builder => $phoneQuery->wherePhoneMatches($search))
                        ->orWhere(fn (Builder $emailQuery): Builder => $emailQuery->whereEmailMatches($search));
                });
        });
    }

    protected function applyCustomerSearch(
        Builder $query,
        string $search,
    ): Builder {
        return $query->where(function (Builder $customerQuery) use ($search): void {
            $customerQuery
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere(fn (Builder $phoneQuery): Builder => $phoneQuery->wherePhoneMatches($search))
                ->orWhere(fn (Builder $emailQuery): Builder => $emailQuery->whereEmailMatches($search));
        });
    }

    protected function customerLabel(Customer $customer): string
    {
        $phone = $customer->phone ? " — {$customer->phone}" : '';
        $status = $customer->status ? " [{$customer->status}]" : '';

        return $customer->full_name.$phone.$status;
    }
}
