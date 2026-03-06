<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Patient;
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
        $customerPhoneHash = Customer::phoneSearchHash($search);
        $customerEmailHash = Customer::emailSearchHash($search);
        $patientPhoneHash = Patient::phoneSearchHash($search);
        $patientEmailHash = Patient::emailSearchHash($search);

        return $query->where(function (Builder $appointmentQuery) use (
            $search,
            $customerPhoneHash,
            $customerEmailHash,
            $patientPhoneHash,
            $patientEmailHash,
        ): void {
            $appointmentQuery
                ->whereHas('customer', function (Builder $customerQuery) use ($search, $customerPhoneHash, $customerEmailHash): void {
                    $this->applyCustomerSearch(
                        query: $customerQuery,
                        search: $search,
                        phoneHash: $customerPhoneHash,
                        emailHash: $customerEmailHash,
                    );
                })
                ->orWhereHas('patient', function (Builder $patientQuery) use ($search, $patientPhoneHash, $patientEmailHash): void {
                    $patientQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('patient_code', 'like', "%{$search}%");

                    if ($patientPhoneHash !== null) {
                        $patientQuery->orWhere('phone_search_hash', $patientPhoneHash);
                    }

                    if ($patientEmailHash !== null) {
                        $patientQuery->orWhere('email_search_hash', $patientEmailHash);
                    }
                });
        });
    }

    protected function applyCustomerSearch(
        Builder $query,
        string $search,
        ?string $phoneHash = null,
        ?string $emailHash = null,
    ): Builder {
        $phoneHash ??= Customer::phoneSearchHash($search);
        $emailHash ??= Customer::emailSearchHash($search);

        return $query->where(function (Builder $customerQuery) use ($search, $phoneHash, $emailHash): void {
            $customerQuery->where('full_name', 'like', "%{$search}%");

            if ($phoneHash !== null) {
                $customerQuery->orWhere('phone_search_hash', $phoneHash);
            }

            if ($emailHash !== null) {
                $customerQuery->orWhere('email_search_hash', $emailHash);
            }
        });
    }

    protected function customerLabel(Customer $customer): string
    {
        $phone = $customer->phone ? " — {$customer->phone}" : '';
        $status = $customer->status ? " [{$customer->status}]" : '';

        return $customer->full_name.$phone.$status;
    }
}
