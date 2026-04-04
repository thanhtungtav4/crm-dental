<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientRiskProfile extends Model
{
    /** @use HasFactory<\Database\Factories\PatientRiskProfileFactory> */
    use HasFactory;

    public const LEVEL_LOW = 'low';

    public const LEVEL_MEDIUM = 'medium';

    public const LEVEL_HIGH = 'high';

    public const MODEL_VERSION_BASELINE = 'risk_baseline_v1';

    protected $fillable = [
        'patient_id',
        'as_of_date',
        'model_version',
        'no_show_risk_score',
        'churn_risk_score',
        'risk_level',
        'recommended_action',
        'generated_at',
        'created_by',
        'feature_payload',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'no_show_risk_score' => 'decimal:2',
            'churn_risk_score' => 'decimal:2',
            'generated_at' => 'datetime',
            'feature_payload' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    public static function levelOptions(): array
    {
        return [
            self::LEVEL_LOW => 'Thấp',
            self::LEVEL_MEDIUM => 'Trung bình',
            self::LEVEL_HIGH => 'Cao',
        ];
    }

    public static function levelLabel(?string $level): string
    {
        return static::levelOptions()[$level ?? ''] ?? 'Không xác định';
    }

    /**
     * @return array{label:string,color:string}
     */
    public static function levelBadgePayload(?string $level): array
    {
        return [
            'label' => static::levelLabel($level),
            'color' => static::levelColor($level),
        ];
    }

    public static function levelColor(?string $level): string
    {
        return match ($level) {
            self::LEVEL_HIGH => 'danger',
            self::LEVEL_MEDIUM => 'warning',
            default => 'success',
        };
    }

    public static function formatScore(float|int|string|null $score): string
    {
        return number_format((float) $score, 2);
    }

    public static function formatCount(int|float|string|null $count): string
    {
        return number_format((float) $count, 0, ',', '.');
    }

    /**
     * @param  array{
     *     total:int,
     *     high:int,
     *     medium:int,
     *     low:int,
     *     average_no_show:float,
     *     average_churn:float,
     *     active_intervention_tickets:int
     * }  $summary
     * @return array<int, array{label:string, value:string}>
     */
    public static function summaryStatsPayload(array $summary): array
    {
        return [
            ['label' => 'Tổng profile', 'value' => static::formatCount($summary['total'])],
            ['label' => 'Risk cao', 'value' => static::formatCount($summary['high'])],
            ['label' => 'Risk trung bình', 'value' => static::formatCount($summary['medium'])],
            ['label' => 'Risk thấp', 'value' => static::formatCount($summary['low'])],
            ['label' => 'Avg no-show risk', 'value' => static::formatScore($summary['average_no_show'])],
            ['label' => 'Avg churn risk', 'value' => static::formatScore($summary['average_churn'])],
            ['label' => 'Ticket can thiệp đang mở', 'value' => static::formatCount($summary['active_intervention_tickets'])],
        ];
    }
}
