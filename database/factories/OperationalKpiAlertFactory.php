<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\OperationalKpiAlert;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OperationalKpiAlert>
 */
class OperationalKpiAlertFactory extends Factory
{
    protected $model = OperationalKpiAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_key' => 'operational_kpi_pack',
            'snapshot_date' => now()->toDateString(),
            'branch_id' => Branch::factory(),
            'snapshot_id' => function (array $attributes) {
                return ReportSnapshot::query()->create([
                    'snapshot_key' => $attributes['snapshot_key'] ?? 'operational_kpi_pack',
                    'snapshot_date' => $attributes['snapshot_date'] ?? now()->toDateString(),
                    'branch_id' => $attributes['branch_id'] ?? Branch::factory()->create()->id,
                    'status' => ReportSnapshot::STATUS_SUCCESS,
                    'sla_status' => ReportSnapshot::SLA_ON_TIME,
                    'generated_at' => now(),
                    'sla_due_at' => now()->addHour(),
                    'payload' => [],
                    'lineage' => ['generated_at' => now()->toIso8601String()],
                ])->id;
            },
            'owner_user_id' => User::factory(),
            'metric_key' => fake()->randomElement(['no_show_rate', 'chair_utilization_rate', 'treatment_acceptance_rate']),
            'threshold_direction' => fake()->randomElement(['max', 'min']),
            'threshold_value' => fake()->numberBetween(50, 90),
            'observed_value' => fake()->numberBetween(0, 100),
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => OperationalKpiAlert::STATUS_NEW,
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(8),
            'metadata' => ['source' => 'factory'],
            'acknowledged_by' => null,
            'acknowledged_at' => null,
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_note' => null,
        ];
    }
}
