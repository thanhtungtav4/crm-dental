<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class ExamSession extends Model
{
    use HasFactory;

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
        $this->status = strtolower(trim($status));
        $this->save();
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
