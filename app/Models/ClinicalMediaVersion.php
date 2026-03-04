<?php

namespace App\Models;

use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalMediaVersion extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicalMediaVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'clinical_media_asset_id',
        'version_number',
        'is_original',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'storage_disk',
        'storage_path',
        'transform_meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'clinical_media_asset_id' => 'integer',
            'version_number' => 'integer',
            'is_original' => 'boolean',
            'file_size_bytes' => 'integer',
            'transform_meta' => 'array',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if (blank($version->version_number)) {
                $latestVersion = self::query()
                    ->where('clinical_media_asset_id', (int) $version->clinical_media_asset_id)
                    ->max('version_number');

                $version->version_number = ((int) $latestVersion) + 1;
            }

            if ($version->is_original === null) {
                $version->is_original = ((int) $version->version_number) === 1;
            }

            if (blank($version->storage_disk)) {
                $assetDisk = null;

                if ($version->clinical_media_asset_id) {
                    $assetDisk = ClinicalMediaAsset::query()
                        ->whereKey((int) $version->clinical_media_asset_id)
                        ->value('storage_disk');
                }

                $version->storage_disk = is_string($assetDisk) && trim($assetDisk) !== ''
                    ? trim($assetDisk)
                    : ClinicRuntimeSettings::clinicalMediaStorageDisk();
            }
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ClinicalMediaAsset::class, 'clinical_media_asset_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ClinicalMediaAccessLog::class, 'clinical_media_version_id');
    }
}
