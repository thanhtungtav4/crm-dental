<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Support\ClinicRuntimeSettings;
use App\Support\ConversationProvider;
use Illuminate\Support\Facades\DB;

class ConversationLeadBindingService
{
    public function __construct(
        protected PatientAssignmentAuthorizer $patientAssignmentAuthorizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prefillForm(Conversation $conversation, ?User $actor): array
    {
        $provider = $conversation->providerEnum();
        $source = $this->leadSource($provider);

        return [
            'full_name' => $conversation->displayName(),
            'phone' => '',
            'email' => '',
            'branch_id' => $conversation->branch_id,
            'assigned_to' => $conversation->assigned_to,
            'notes' => '',
            'source' => $source,
            'source_detail' => $provider?->customerSourceDetail() ?? 'conversation_inbox',
            'status' => $this->leadStatus(),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLead(Conversation $conversation, array $data, ?User $actor): Customer
    {
        $provider = $conversation->providerEnum();
        $payload = array_merge(
            $this->prefillForm($conversation, $actor),
            $data,
        );

        $payload['full_name'] = trim((string) ($payload['full_name'] ?? ''));
        $payload['phone'] = $this->nullableString($payload['phone'] ?? null);
        $payload['email'] = $this->nullableString($payload['email'] ?? null);
        $payload['notes'] = $this->nullableString($payload['notes'] ?? null);
        $payload['source'] = $this->leadSource($provider);
        $payload['source_detail'] = $provider?->customerSourceDetail() ?? 'conversation_inbox';
        $payload['status'] = $this->leadStatus();
        $payload['created_by'] = $actor?->id;
        $payload['updated_by'] = $actor?->id;

        $payload = $this->patientAssignmentAuthorizer->sanitizeCustomerFormData($actor, $payload);

        return DB::transaction(function () use ($conversation, $payload): Customer {
            $customer = Customer::query()->create($payload);

            $conversation->forceFill([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'assigned_to' => $customer->assigned_to ?: $conversation->assigned_to,
            ])->save();

            return $customer;
        }, 3);
    }

    protected function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function leadStatus(): string
    {
        $options = ClinicRuntimeSettings::customerStatusOptions();

        if (array_key_exists('lead', $options)) {
            return 'lead';
        }

        return ClinicRuntimeSettings::defaultCustomerStatus();
    }

    protected function leadSource(?ConversationProvider $provider): string
    {
        $source = $provider?->customerSource();
        $availableSources = ClinicRuntimeSettings::customerSourceOptions();

        if (is_string($source) && array_key_exists($source, $availableSources)) {
            return $source;
        }

        return ClinicRuntimeSettings::defaultCustomerSource();
    }
}
