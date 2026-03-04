<?php

namespace Database\Factories;

use App\Models\ClinicalMediaAsset;
use App\Models\ClinicalMediaVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClinicalMediaVersion>
 */
class ClinicalMediaVersionFactory extends Factory
{
    protected $model = ClinicalMediaVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_media_asset_id' => ClinicalMediaAsset::factory(),
            'version_number' => 1,
            'is_original' => true,
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => fake()->numberBetween(10_000, 8_000_000),
            'checksum_sha256' => hash('sha256', (string) Str::uuid()),
            'storage_disk' => 'public',
            'storage_path' => 'clinical-media/versions/'.Str::ulid().'.jpg',
            'transform_meta' => null,
            'created_by' => User::factory(),
        ];
    }
}
