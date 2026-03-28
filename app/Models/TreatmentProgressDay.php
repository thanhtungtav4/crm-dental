<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class TreatmentProgressDay extends Model
{
    use HasFactory, SoftDeletes;

    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'patient_id',
        'exam_session_id',
        'treatment_plan_id',
        'branch_id',
        'progress_date',
        'status',
        'notes',
        'started_at',
        'completed_at',
        'locked_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_session_id' => 'integer',
            'treatment_plan_id' => 'integer',
            'branch_id' => 'integer',
            'progress_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $day): void {
            $day->status = static::normalizeStatus($day->status) ?? static::STATUS_PLANNED;

            if ($day->exists && $day->isDirty('status') && ! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'TREATMENT_PROGRESS_DAY_STATE_INVALID: Trang thai ngay tien trinh chi duoc thay doi qua TreatmentProgressSyncService.',
                ]);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TreatmentProgressItem::class, 'treatment_progress_day_id');
    }

    public static function normalizeStatus(?string $status): ?string
    {
        $normalizedStatus = strtolower(trim((string) $status));

        return in_array($normalizedStatus, [
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_LOCKED,
        ], true) ? $normalizedStatus : null;
    }

    public static function runWithinManagedWorkflow(callable $callback): mixed
    {
        $previousState = static::$allowsManagedWorkflowMutation;
        static::$allowsManagedWorkflowMutation = true;

        try {
            return $callback();
        } finally {
            static::$allowsManagedWorkflowMutation = $previousState;
        }
    }
}
