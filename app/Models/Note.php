<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Validation\ValidationException;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_GENERAL = 'general';

    public const CARE_STATUS_NOT_STARTED = 'not_started';
    public const CARE_STATUS_IN_PROGRESS = 'in_progress';
    public const CARE_STATUS_DONE = 'done';
    public const CARE_STATUS_NEED_FOLLOWUP = 'need_followup';
    public const CARE_STATUS_FAILED = 'failed';

    public const DEFAULT_CARE_STATUS = self::CARE_STATUS_DONE;

    protected const LEGACY_CARE_STATUS_MAP = [
        'planned' => self::CARE_STATUS_NOT_STARTED,
        'pending' => self::CARE_STATUS_NOT_STARTED,
        'not_started' => self::CARE_STATUS_NOT_STARTED,
        'not-started' => self::CARE_STATUS_NOT_STARTED,
        'not started' => self::CARE_STATUS_NOT_STARTED,
        'in_progress' => self::CARE_STATUS_IN_PROGRESS,
        'in-progress' => self::CARE_STATUS_IN_PROGRESS,
        'in progress' => self::CARE_STATUS_IN_PROGRESS,
        'completed' => self::CARE_STATUS_DONE,
        'done' => self::CARE_STATUS_DONE,
        'no_response' => self::CARE_STATUS_NEED_FOLLOWUP,
        'need_followup' => self::CARE_STATUS_NEED_FOLLOWUP,
        'need-followup' => self::CARE_STATUS_NEED_FOLLOWUP,
        'need followup' => self::CARE_STATUS_NEED_FOLLOWUP,
        'cancelled' => self::CARE_STATUS_FAILED,
        'canceled' => self::CARE_STATUS_FAILED,
        'failed' => self::CARE_STATUS_FAILED,
    ];

    protected const CARE_STATUS_LABELS = [
        self::CARE_STATUS_NOT_STARTED => 'Chưa chăm sóc',
        self::CARE_STATUS_IN_PROGRESS => 'Đang chăm sóc',
        self::CARE_STATUS_DONE => 'Hoàn thành',
        self::CARE_STATUS_NEED_FOLLOWUP => 'Cần chăm sóc lại',
        self::CARE_STATUS_FAILED => 'Đã hủy/Thất bại',
    ];

    protected const CARE_STATUS_COLORS = [
        self::CARE_STATUS_NOT_STARTED => 'warning',
        self::CARE_STATUS_IN_PROGRESS => 'primary',
        self::CARE_STATUS_DONE => 'success',
        self::CARE_STATUS_NEED_FOLLOWUP => 'info',
        self::CARE_STATUS_FAILED => 'danger',
    ];

    protected const CARE_STATUS_TRANSITIONS = [
        self::CARE_STATUS_NOT_STARTED => [
            self::CARE_STATUS_IN_PROGRESS,
            self::CARE_STATUS_DONE,
            self::CARE_STATUS_NEED_FOLLOWUP,
            self::CARE_STATUS_FAILED,
        ],
        self::CARE_STATUS_IN_PROGRESS => [
            self::CARE_STATUS_DONE,
            self::CARE_STATUS_NEED_FOLLOWUP,
            self::CARE_STATUS_FAILED,
        ],
        self::CARE_STATUS_NEED_FOLLOWUP => [
            self::CARE_STATUS_NOT_STARTED,
            self::CARE_STATUS_IN_PROGRESS,
            self::CARE_STATUS_DONE,
            self::CARE_STATUS_FAILED,
        ],
        self::CARE_STATUS_FAILED => [
            self::CARE_STATUS_NOT_STARTED,
            self::CARE_STATUS_IN_PROGRESS,
        ],
        self::CARE_STATUS_DONE => [],
    ];

    protected $fillable = [
        'patient_id',
        'customer_id',
        'user_id',
        'type',
        'care_type',
        'care_channel',
        'care_status',
        'care_at',
        'care_mode',
        'is_recurring',
        'content',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'care_at' => 'datetime',
        'is_recurring' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $note): void {
            $normalizedStatus = static::normalizeCareStatus($note->care_status) ?? static::DEFAULT_CARE_STATUS;
            $note->care_status = $normalizedStatus;

            if (! $note->exists || ! $note->isDirty('care_status')) {
                return;
            }

            $fromStatus = static::normalizeCareStatus((string) $note->getOriginal('care_status')) ?? static::DEFAULT_CARE_STATUS;

            if (! static::canTransitionCareStatus($fromStatus, $normalizedStatus)) {
                throw ValidationException::withMessages([
                    'care_status' => sprintf(
                        'CARE_TICKET_STATE_INVALID: Không thể chuyển từ "%s" sang "%s".',
                        static::careStatusLabel($fromStatus),
                        static::careStatusLabel($normalizedStatus),
                    ),
                ]);
            }
        });
    }

    protected function careStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => static::normalizeCareStatus($value) ?? static::DEFAULT_CARE_STATUS,
            set: fn ($value) => static::normalizeCareStatus($value) ?? static::DEFAULT_CARE_STATUS,
        );
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public static function careStatusOptions(): array
    {
        return static::CARE_STATUS_LABELS;
    }

    public static function careStatusValues(): array
    {
        return array_keys(static::CARE_STATUS_LABELS);
    }

    public static function careStatusLabel(?string $status): string
    {
        $normalized = static::normalizeCareStatus($status);

        if ($normalized === null) {
            return 'Chưa xác định';
        }

        return static::CARE_STATUS_LABELS[$normalized] ?? 'Chưa xác định';
    }

    public static function careStatusColor(?string $status): string
    {
        $normalized = static::normalizeCareStatus($status);

        if ($normalized === null) {
            return 'gray';
        }

        return static::CARE_STATUS_COLORS[$normalized] ?? 'gray';
    }

    public static function normalizeCareStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return static::LEGACY_CARE_STATUS_MAP[$normalized] ?? $normalized;
    }

    public static function statusesForQuery(array $statuses): array
    {
        $expanded = [];

        foreach ($statuses as $status) {
            $normalized = static::normalizeCareStatus($status);
            if ($normalized === null) {
                continue;
            }

            $expanded[] = $normalized;
            $expanded[] = strtoupper($normalized);

            foreach (static::legacyAliasesForCareStatus($normalized) as $alias) {
                $expanded[] = $alias;
                $expanded[] = strtoupper($alias);
            }
        }

        return array_values(array_unique($expanded));
    }

    public static function canTransitionCareStatus(?string $fromStatus, ?string $toStatus): bool
    {
        $from = static::normalizeCareStatus($fromStatus);
        $to = static::normalizeCareStatus($toStatus);

        if ($from === null || $to === null) {
            return false;
        }

        if ($from === $to) {
            return true;
        }

        $allowed = static::CARE_STATUS_TRANSITIONS[$from] ?? static::careStatusValues();

        return in_array($to, $allowed, true);
    }

    public static function activeCareStatuses(): array
    {
        return [
            self::CARE_STATUS_NOT_STARTED,
            self::CARE_STATUS_IN_PROGRESS,
            self::CARE_STATUS_NEED_FOLLOWUP,
        ];
    }

    protected static function legacyAliasesForCareStatus(string $status): array
    {
        $aliases = [];

        foreach (static::LEGACY_CARE_STATUS_MAP as $alias => $canonicalStatus) {
            if ($canonicalStatus === $status) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }
}
