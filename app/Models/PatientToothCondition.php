<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientToothCondition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'tooth_number',
        'tooth_condition_id',
        'treatment_status',
        'treatment_plan_id',
        'notes',
        'diagnosed_at',
        'completed_at',
        'diagnosed_by',
    ];

    protected $casts = [
        'diagnosed_at' => 'date',
        'completed_at' => 'date',
    ];

    // Treatment status constants
    const STATUS_CURRENT = 'current';
    const STATUS_IN_TREATMENT = 'in_treatment';
    const STATUS_COMPLETED = 'completed';

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(ToothCondition::class, 'tooth_condition_id');
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function diagnosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }

    // Get status options
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_CURRENT => 'Tình trạng hiện tại',
            self::STATUS_IN_TREATMENT => 'Đang điều trị',
            self::STATUS_COMPLETED => 'Hoàn thành điều trị',
        ];
    }

    // Get status color for UI
    public function getStatusColorAttribute(): string
    {
        return match ($this->treatment_status) {
            self::STATUS_CURRENT => '#6B7280', // Gray
            self::STATUS_IN_TREATMENT => '#EF4444', // Red
            self::STATUS_COMPLETED => '#10B981', // Green
            default => '#6B7280',
        };
    }

    // Get status label
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->treatment_status] ?? $this->treatment_status;
    }

    // Adult teeth numbers
    public static function getAdultTeethUpper(): array
    {
        return ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
    }

    public static function getAdultTeethLower(): array
    {
        return ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
    }

    // Child teeth numbers (deciduous/baby teeth)
    public static function getChildTeethUpper(): array
    {
        return ['55', '54', '53', '52', '51', '61', '62', '63', '64', '65'];
    }

    public static function getChildTeethLower(): array
    {
        return ['85', '84', '83', '82', '81', '71', '72', '73', '74', '75'];
    }

    // Get all teeth numbers
    public static function getAllTeethNumbers(): array
    {
        return array_merge(
            self::getAdultTeethUpper(),
            self::getChildTeethUpper(),
            self::getChildTeethLower(),
            self::getAdultTeethLower()
        );
    }

    // Scopes
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForTooth($query, string $toothNumber)
    {
        return $query->where('tooth_number', $toothNumber);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('treatment_status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('treatment_status', [self::STATUS_CURRENT, self::STATUS_IN_TREATMENT]);
    }

    // Mark as in treatment
    public function startTreatment(?int $treatmentPlanId = null): void
    {
        $this->update([
            'treatment_status' => self::STATUS_IN_TREATMENT,
            'treatment_plan_id' => $treatmentPlanId,
        ]);
    }

    // Mark as completed
    public function completeTreatment(): void
    {
        $this->update([
            'treatment_status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    // Boot method
    protected static function booted(): void
    {
        static::creating(function (PatientToothCondition $condition) {
            if (empty($condition->diagnosed_at)) {
                $condition->diagnosed_at = now();
            }

            if (empty($condition->diagnosed_by) && auth()->check()) {
                $condition->diagnosed_by = auth()->id();
            }
        });
    }
}
