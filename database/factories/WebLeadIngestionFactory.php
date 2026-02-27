<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebLeadIngestion>
 */
class WebLeadIngestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => 'req-'.strtolower(fake()->bothify('????????????')),
            'source' => 'website',
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('09########'),
            'phone_normalized' => fake()->numerify('09########'),
            'branch_code' => null,
            'branch_id' => null,
            'customer_id' => null,
            'status' => \App\Models\WebLeadIngestion::STATUS_PENDING,
            'payload' => [],
            'response' => [],
            'error_message' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Pest Test Agent',
            'received_at' => now(),
            'processed_at' => null,
        ];
    }
}
