<?php

namespace App\Models;

use App\Services\ExamSessionWorkflowService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ExamSession extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_LOCKED = 'locked';

    protected const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS],
        self::STATUS_PLANNED => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_LOCKED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_LOCKED],
        self::STATUS_COMPLETED => [self::STATUS_LOCKED],
        self::STATUS_LOCKED => [],
    ];

    protected $fillable = [
        'patient_id',
        'visit_episode_id',
        'branch_id',
        'doctor_id',
        'session_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_episode_id' => 'integer',
            'branch_id' => 'integer',
            'doctor_id' => 'integer',
            'session_date' => 'date',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $session): void {
            $session->status = strtolower(trim((string) ($session->status ?: self::STATUS_DRAFT)));

            if (! in_array($session->status, self::allStatuses(), true)) {
                $session->status = self::STATUS_DRAFT;
            }

            if ($session->exists && $session->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'EXAM_SESSION_STATE_INVALID: Trang thai phien kham chi duoc thay doi qua ExamSessionWorkflowService.',
                    ]);
                }

                $fromStatus = strtolower(trim((string) ($session->getOriginal('status') ?: self::STATUS_DRAFT)));
                $toStatus = $session->status;

                if (! self::canTransition($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => 'EXAM_SESSION_STATE_INVALID: Không thể chuyển trạng thái phiên khám.',
                    ]);
                }
            }

            if (! $session->session_date) {
                $session->session_date = now()->toDateString();
            }
        });
    }

    public function getDateAttribute(): mixed
    {
        return $this->session_date;
    }

    public function setDateAttribute($value): void
    {
        $this->attributes['session_date'] = $value;
    }

    public static function allStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_LOCKED,
        ];
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    public function transitionTo(string $status): void
    {
        app(ExamSessionWorkflowService::class)->transition($this, $status);
    }

    public static function runWithinManagedWorkflow(callable $callback, array $context = []): mixed
    {
        $previousState = static::$allowsManagedWorkflowMutation;
        $previousContext = static::$managedTransitionContext;
        static::$allowsManagedWorkflowMutation = true;
        static::$managedTransitionContext = $context;

        try {
            return $callback();
        } finally {
            static::$allowsManagedWorkflowMutation = $previousState;
            static::$managedTransitionContext = $previousContext;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function currentManagedTransitionContext(): array
    {
        return static::$managedTransitionContext;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitEpisode(): BelongsTo
    {
        return $this->belongsTo(VisitEpisode::class, 'visit_episode_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function clinicalNote(): HasOne
    {
        return $this->hasOne(ClinicalNote::class)->latestOfMany('id');
    }

    public function clinicalOrders(): HasMany
    {
        return $this->hasMany(ClinicalOrder::class);
    }

    public function clinicalMediaAssets(): HasMany
    {
        return $this->hasMany(ClinicalMediaAsset::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function treatmentProgressDays(): HasMany
    {
        return $this->hasMany(TreatmentProgressDay::class);
    }

    public function treatmentProgressItems(): HasMany
    {
        return $this->hasMany(TreatmentProgressItem::class);
    }
}
