<?php

namespace Database\Factories;

use App\Models\ExamSession;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamSession>
 */
class ExamSessionFactory extends Factory
{
    protected $model = ExamSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'visit_episode_id' => null,
            'branch_id' => null,
            'doctor_id' => User::factory(),
            'session_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => fake()->randomElement([
                ExamSession::STATUS_DRAFT,
                ExamSession::STATUS_PLANNED,
                ExamSession::STATUS_IN_PROGRESS,
            ]),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
