<?php

namespace Database\Factories;

use App\Models\ClinicalMediaAsset;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalMediaAsset>
 */
class ClinicalMediaAssetFactory extends Factory
{
    protected $model = ClinicalMediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'visit_episode_id' => null,
            'exam_session_id' => null,
            'plan_item_id' => null,
            'treatment_session_id' => null,
            'clinical_order_id' => null,
            'clinical_result_id' => null,
            'prescription_id' => null,
            'branch_id' => null,
            'captured_by' => User::factory(),
            'consent_id' => null,
            'captured_at' => now(),
            'modality' => ClinicalMediaAsset::MODALITY_PHOTO,
            'anatomy_scope' => fake()->randomElement(['intraoral', 'extraoral', 'panorama']),
            'phase' => fake()->randomElement(['baseline', 'pre', 'intra', 'post', 'followup']),
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => fake()->numberBetween(10_000, 8_000_000),
            'checksum_sha256' => hash('sha256', (string) Str::uuid()),
            'storage_disk' => 'public',
            'storage_path' => 'clinical-media/'.Str::ulid().'.jpg',
            'status' => ClinicalMediaAsset::STATUS_ACTIVE,
            'retention_class' => ClinicalMediaAsset::RETENTION_CLINICAL_OPERATIONAL,
            'legal_hold' => false,
            'meta' => [],
        ];
    }
}
