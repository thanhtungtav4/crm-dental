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
}
