<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ClinicalMediaAccessLog extends Model
{
    /** @use HasFactory<\Database\Factories\ClinicalMediaAccessLogFactory> */
    use HasFactory;

    public const ACTION_VIEW = 'view';

    public const ACTION_DOWNLOAD = 'download';

    public const ACTION_SHARE = 'share';

    public const ACTION_DELETE = 'delete';

    protected $fillable = [
        'clinical_media_asset_id',
        'clinical_media_version_id',
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'actor_id',
        'action',
        'ip_address',
        'user_agent',
        'purpose',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'clinical_media_asset_id' => 'integer',
            'clinical_media_version_id' => 'integer',
            'patient_id' => 'integer',
            'visit_episode_id' => 'integer',
            'branch_id' => 'integer',
            'actor_id' => 'integer',
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->occurred_at = $log->occurred_at ?? now();

            if ($log->patient_id === null && $log->clinical_media_asset_id !== null) {
                $patientId = ClinicalMediaAsset::query()
                    ->whereKey((int) $log->clinical_media_asset_id)
                    ->value('patient_id');

                $log->patient_id = $patientId !== null ? (int) $patientId : null;
            }

            if ($log->visit_episode_id === null && $log->clinical_media_asset_id !== null) {
                $visitEpisodeId = ClinicalMediaAsset::query()
                    ->whereKey((int) $log->clinical_media_asset_id)
                    ->value('visit_episode_id');

                $log->visit_episode_id = $visitEpisodeId !== null ? (int) $visitEpisodeId : null;
            }

            if ($log->branch_id === null && $log->clinical_media_asset_id !== null) {
                $branchId = ClinicalMediaAsset::query()
                    ->whereKey((int) $log->clinical_media_asset_id)
                    ->value('branch_id');

                $log->branch_id = $branchId !== null ? (int) $branchId : null;
            }
        });

        static::updating(function (): void {
            throw ValidationException::withMessages([
                'clinical_media_access_log' => 'Clinical media access log là immutable, không cho phép cập nhật.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'clinical_media_access_log' => 'Clinical media access log là immutable, không cho phép xóa.',
            ]);
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ClinicalMediaAsset::class, 'clinical_media_asset_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ClinicalMediaVersion::class, 'clinical_media_version_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'visit_episode_id');
    }

    public function visitEpisode(): BelongsTo
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }
}
