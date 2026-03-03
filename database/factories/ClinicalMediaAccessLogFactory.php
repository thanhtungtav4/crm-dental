<?php

namespace Database\Factories;

use App\Models\ClinicalMediaAccessLog;
use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalMediaAccessLog>
 */
class ClinicalMediaAccessLogFactory extends Factory
{
    protected $model = ClinicalMediaAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_media_asset_id' => ClinicalMediaAsset::factory(),
            'clinical_media_version_id' => null,
            'patient_id' => null,
            'visit_episode_id' => null,
            'branch_id' => null,
            'actor_id' => User::factory(),
            'action' => fake()->randomElement([
                ClinicalMediaAccessLog::ACTION_VIEW,
                ClinicalMediaAccessLog::ACTION_DOWNLOAD,
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'purpose' => fake()->optional()->sentence(3),
            'context' => [],
            'occurred_at' => now(),
        ];
    }

    public function forVersion(ClinicalMediaVersion $version): static
    {
        return $this->state(fn (): array => [
            'clinical_media_asset_id' => $version->clinical_media_asset_id,
            'clinical_media_version_id' => $version->id,
        ]);
    }
}
