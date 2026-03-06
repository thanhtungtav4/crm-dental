<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Facades\DB;

class PatientOnboardingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data): Patient {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = $this->createCompanionCustomer($data)->id;
            }

            return Patient::query()->create($data);
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createCompanionCustomer(array $data): Customer
    {
        return Customer::query()->create([
            'branch_id' => isset($data['first_branch_id']) && filled($data['first_branch_id']) ? (int) $data['first_branch_id'] : null,
            'full_name' => (string) ($data['full_name'] ?? ''),
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'source' => ClinicRuntimeSettings::defaultCustomerSource(),
            'customer_group_id' => isset($data['customer_group_id']) && filled($data['customer_group_id']) ? (int) $data['customer_group_id'] : null,
            'promotion_group_id' => isset($data['promotion_group_id']) && filled($data['promotion_group_id']) ? (int) $data['promotion_group_id'] : null,
            'status' => ClinicRuntimeSettings::defaultCustomerStatus(),
            'notes' => $this->customerNote($data),
            'assigned_to' => isset($data['owner_staff_id']) && filled($data['owner_staff_id']) ? (int) $data['owner_staff_id'] : null,
            'created_by' => auth()->id() ?? (isset($data['created_by']) && filled($data['created_by']) ? (int) $data['created_by'] : null),
            'updated_by' => auth()->id() ?? (isset($data['updated_by']) && filled($data['updated_by']) ? (int) $data['updated_by'] : null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function customerNote(array $data): ?string
    {
        $note = isset($data['note']) && filled($data['note'])
            ? trim((string) $data['note'])
            : null;

        if ($note !== null) {
            return $note;
        }

        $firstVisitReason = isset($data['first_visit_reason']) && filled($data['first_visit_reason'])
            ? trim((string) $data['first_visit_reason'])
            : null;

        return $firstVisitReason ?? 'Auto-created from Patient';
    }
}
