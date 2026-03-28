<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class VisitEpisode extends Model
{
    use HasFactory, SoftDeletes;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const DEFAULT_STATUS = self::STATUS_SCHEDULED;

    protected const STATUS_TRANSITIONS = [
        self::STATUS_SCHEDULED => [
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
            self::STATUS_CANCELLED,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_IN_PROGRESS => [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_NO_SHOW => [
            self::STATUS_SCHEDULED,
            self::STATUS_RESCHEDULED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_RESCHEDULED => [
            self::STATUS_SCHEDULED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CANCELLED => [
            self::STATUS_SCHEDULED,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_COMPLETED => [],
    ];

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',
        'branch_id',
        'chair_code',
        'status',
        'scheduled_at',
        'check_in_at',
        'arrived_at',
        'in_chair_at',
        'check_out_at',
        'planned_duration_minutes',
        'actual_duration_minutes',
        'waiting_minutes',
        'chair_minutes',
        'overrun_minutes',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'check_in_at' => 'datetime',
        'arrived_at' => 'datetime',
        'in_chair_at' => 'datetime',
        'check_out_at' => 'datetime',
        'planned_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'waiting_minutes' => 'integer',
        'chair_minutes' => 'integer',
        'overrun_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $visitEpisode): void {
            $visitEpisode->status = static::normalizeStatus($visitEpisode->status) ?? static::DEFAULT_STATUS;

            if ($visitEpisode->exists && $visitEpisode->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'VISIT_EPISODE_STATE_INVALID: Trang thai luot kham chi duoc thay doi qua VisitEpisodeService.',
                    ]);
                }

                $fromStatus = static::normalizeStatus((string) ($visitEpisode->getOriginal('status') ?? static::DEFAULT_STATUS))
                    ?? static::DEFAULT_STATUS;

                if (! static::canTransition($fromStatus, $visitEpisode->status)) {
                    throw ValidationException::withMessages([
                        'status' => 'VISIT_EPISODE_STATE_INVALID: Khong the chuyen trang thai luot kham.',
                    ]);
                }
            }
        });
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function clinicalNotes()
    {
        return $this->hasMany(ClinicalNote::class, 'visit_episode_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'visit_episode_id');
    }

    public function clinicalOrders()
    {
        return $this->hasMany(ClinicalOrder::class, 'visit_episode_id');
    }

    public function clinicalResults()
    {
        return $this->hasMany(ClinicalResult::class, 'visit_episode_id');
    }

    public function clinicalMediaAssets()
    {
        return $this->hasMany(ClinicalMediaAsset::class, 'visit_episode_id');
    }

    public static function normalizeStatus(?string $status): ?string
    {
        $normalizedStatus = strtolower(trim((string) $status));

        return in_array($normalizedStatus, [
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
            self::STATUS_CANCELLED,
            self::STATUS_RESCHEDULED,
        ], true) ? $normalizedStatus : null;
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, static::STATUS_TRANSITIONS[$fromStatus] ?? [], true);
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
}
