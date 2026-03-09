<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebLeadEmailDelivery;
use App\Models\WebLeadIngestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebLeadEmailDelivery>
 */
class WebLeadEmailDeliveryFactory extends Factory
{
    protected $model = WebLeadEmailDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $ingestion = WebLeadIngestion::factory()->create([
            'branch_id' => $branch->id,
            'branch_code' => $branch->code,
            'customer_id' => $customer->id,
            'status' => WebLeadIngestion::STATUS_CREATED,
        ]);
        $recipient = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [
            'web_lead_ingestion_id' => $ingestion->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'recipient_user_id' => $recipient->id,
            'dedupe_key' => hash('sha256', 'web-lead-email|'.Str::uuid()),
            'recipient_type' => WebLeadEmailDelivery::RECIPIENT_TYPE_USER,
            'recipient_email' => $recipient->email,
            'recipient_name' => $recipient->name,
            'status' => WebLeadEmailDelivery::STATUS_QUEUED,
            'processing_token' => null,
            'locked_at' => null,
            'attempt_count' => 0,
            'manual_resend_count' => 0,
            'last_attempt_at' => null,
            'next_retry_at' => null,
            'sent_at' => null,
            'transport_message_id' => null,
            'last_error_message' => null,
            'payload' => [
                'subject' => '[CRM Lead] Web lead mới',
                'customer_name' => $customer->full_name,
                'customer_phone' => $customer->phone,
                'branch_name' => $branch->name,
                'request_id' => $ingestion->request_id,
                'customer_url' => null,
                'frontdesk_url' => null,
                'note' => null,
            ],
            'mailer_snapshot' => [
                'host' => 'smtp.example.test',
                'port' => 587,
                'scheme' => 'tls',
                'from_address' => 'lead-bot@example.test',
                'from_name' => 'CRM Lead Bot',
                'queue' => 'web-lead-mail',
                'max_attempts' => 5,
                'retry_delay_minutes' => 10,
            ],
        ];
    }
}
