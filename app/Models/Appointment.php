<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_RESCHEDULED = 'rescheduled';

    public const DEFAULT_STATUS = self::STATUS_SCHEDULED;

    protected const LEGACY_STATUS_MAP = [
        'pending' => self::STATUS_SCHEDULED,
        'booked' => self::STATUS_SCHEDULED,
        'new' => self::STATUS_SCHEDULED,
        'scheduled' => self::STATUS_SCHEDULED,
        'confirmed' => self::STATUS_CONFIRMED,
        'arrived' => self::STATUS_CONFIRMED,
        'in_treatment' => self::STATUS_IN_PROGRESS,
        'in-treatment' => self::STATUS_IN_PROGRESS,
        'in treatment' => self::STATUS_IN_PROGRESS,
        'examining' => self::STATUS_IN_PROGRESS,
        'in_progress' => self::STATUS_IN_PROGRESS,
        'in-progress' => self::STATUS_IN_PROGRESS,
        'done' => self::STATUS_COMPLETED,
        'finished' => self::STATUS_COMPLETED,
        'completed' => self::STATUS_COMPLETED,
        'canceled' => self::STATUS_CANCELLED,
        'cancel' => self::STATUS_CANCELLED,
        'cancelled' => self::STATUS_CANCELLED,
        'later' => self::STATUS_RESCHEDULED,
        'rebooked' => self::STATUS_RESCHEDULED,
        're-booked' => self::STATUS_RESCHEDULED,
        're_booked' => self::STATUS_RESCHEDULED,
        'reschedule' => self::STATUS_RESCHEDULED,
        'rescheduled' => self::STATUS_RESCHEDULED,
        'no_show' => self::STATUS_NO_SHOW,
        'no-show' => self::STATUS_NO_SHOW,
        'no show' => self::STATUS_NO_SHOW,
    ];

    protected const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Đã đặt',
        self::STATUS_CONFIRMED => 'Đã xác nhận',
        self::STATUS_IN_PROGRESS => 'Đang khám',
        self::STATUS_COMPLETED => 'Hoàn thành',
        self::STATUS_RESCHEDULED => 'Đã hẹn lại',
        self::STATUS_CANCELLED => 'Đã hủy',
        self::STATUS_NO_SHOW => 'Không đến',
    ];

    protected const STATUS_COLORS = [
        self::STATUS_SCHEDULED => 'warning',
        self::STATUS_CONFIRMED => 'primary',
        self::STATUS_IN_PROGRESS => 'info',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_RESCHEDULED => 'gray',
        self::STATUS_CANCELLED => 'danger',
        self::STATUS_NO_SHOW => 'gray',
    ];

    protected const STATUS_ICONS = [
        self::STATUS_SCHEDULED => 'heroicon-o-clock',
        self::STATUS_CONFIRMED => 'heroicon-o-check-circle',
        self::STATUS_IN_PROGRESS => 'heroicon-o-arrow-path',
        self::STATUS_COMPLETED => 'heroicon-o-check-badge',
        self::STATUS_RESCHEDULED => 'heroicon-o-arrow-path',
        self::STATUS_CANCELLED => 'heroicon-o-x-circle',
        self::STATUS_NO_SHOW => 'heroicon-o-x-circle',
    ];

    protected const STATUS_TRANSITIONS = [
        self::STATUS_SCHEDULED => [
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_CONFIRMED => [
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
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
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_CANCELLED => [
            self::STATUS_SCHEDULED,
            self::STATUS_RESCHEDULED,
        ],
        self::STATUS_COMPLETED => [],
    ];

    protected $fillable = [
        'customer_id',
        'patient_id',
        'doctor_id',
        'assigned_to',
        'branch_id',
        'date',
        'appointment_type',
        'appointment_kind',
        'duration_minutes',
        'status',
        'note',
        'chief_complaint',
        'internal_notes',
        'cancellation_reason',
        'reschedule_reason',
        'reminder_hours',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'confirmed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'reminder_hours' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $appointment): void {
            $normalizedStatus = static::normalizeStatus($appointment->status) ?? static::DEFAULT_STATUS;
            $appointment->status = $normalizedStatus;

            if ($appointment->exists && $appointment->isDirty('status')) {
                $fromStatus = static::normalizeStatus((string) $appointment->getOriginal('status')) ?? static::DEFAULT_STATUS;

                if (! static::canTransition($fromStatus, $normalizedStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf(
                            'APPOINTMENT_STATE_INVALID: Không thể chuyển từ "%s" sang "%s".',
                            static::statusLabel($fromStatus),
                            static::statusLabel($normalizedStatus),
                        ),
                    ]);
                }
            }

            static::assertStatusReasonRequirement($appointment, $normalizedStatus);
        });
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => static::normalizeStatus($value) ?? static::DEFAULT_STATUS,
            set: fn ($value) => static::normalizeStatus($value) ?? static::DEFAULT_STATUS,
        );
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function doctor() { return $this->belongsTo(User::class, 'doctor_id'); }
    public function assignedTo() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by'); }

    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    public static function statusValues(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    public static function activeStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
        ];
    }

    public static function statusesRequiringConfirmation(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
        ];
    }

    public static function statusesForQuery(array $statuses): array
    {
        $expanded = [];

        foreach ($statuses as $status) {
            $normalized = static::normalizeStatus($status);
            if ($normalized === null) {
                continue;
            }

            $expanded[] = $normalized;
            $expanded[] = strtoupper($normalized);

            foreach (static::legacyAliasesFor($normalized) as $alias) {
                $expanded[] = $alias;
                $expanded[] = strtoupper($alias);
            }
        }

        return array_values(array_unique($expanded));
    }

    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return static::LEGACY_STATUS_MAP[$normalized] ?? $normalized;
    }

    public static function canTransition(?string $fromStatus, ?string $toStatus): bool
    {
        $from = static::normalizeStatus($fromStatus);
        $to = static::normalizeStatus($toStatus);

        if ($from === null || $to === null) {
            return false;
        }

        if ($from === $to) {
            return true;
        }

        $allowed = static::STATUS_TRANSITIONS[$from] ?? static::statusValues();

        return in_array($to, $allowed, true);
    }

    public static function allowedNextStatuses(?string $fromStatus): array
    {
        $from = static::normalizeStatus($fromStatus) ?? static::DEFAULT_STATUS;

        return static::STATUS_TRANSITIONS[$from] ?? [];
    }

    public static function statusLabel(?string $status): string
    {
        $normalized = static::normalizeStatus($status);

        if ($normalized === null) {
            return 'Không xác định';
        }

        return static::STATUS_LABELS[$normalized] ?? 'Không xác định';
    }

    public static function statusColor(?string $status): string
    {
        $normalized = static::normalizeStatus($status);

        if ($normalized === null) {
            return 'gray';
        }

        return static::STATUS_COLORS[$normalized] ?? 'gray';
    }

    public static function statusIcon(?string $status): string
    {
        $normalized = static::normalizeStatus($status);

        if ($normalized === null) {
            return 'heroicon-o-information-circle';
        }

        return static::STATUS_ICONS[$normalized] ?? 'heroicon-o-information-circle';
    }

    protected static function legacyAliasesFor(string $status): array
    {
        $aliases = [];

        foreach (static::LEGACY_STATUS_MAP as $alias => $canonicalStatus) {
            if ($canonicalStatus === $status) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    protected static function assertStatusReasonRequirement(self $appointment, string $status): void
    {
        if (! $appointment->isDirty('status')) {
            return;
        }

        $originalStatus = $appointment->exists
            ? static::normalizeStatus((string) $appointment->getOriginal('status'))
            : null;

        $statusChanged = ! $appointment->exists || $originalStatus !== $status;
        if (! $statusChanged) {
            return;
        }

        if ($status === self::STATUS_CANCELLED && blank($appointment->cancellation_reason)) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'Vui lòng nhập lý do hủy lịch hẹn.',
            ]);
        }

        if ($status === self::STATUS_RESCHEDULED && blank($appointment->reschedule_reason)) {
            throw ValidationException::withMessages([
                'reschedule_reason' => 'Vui lòng nhập lý do hẹn lại lịch hẹn.',
            ]);
        }
    }

    public function getTimeRangeLabelAttribute(): string
    {
        if (!$this->date) {
            return '-';
        }

        $start = $this->date->copy();
        $end = $this->date->copy()->addMinutes($this->duration_minutes ?: 0);

        return $start->format('H:i') . '-' . $end->format('H:i');
    }

    public function getAppointmentKindLabelAttribute(): string
    {
        return match ($this->appointment_kind) {
            'booking' => 'Đặt hẹn',
            're_exam' => 'Tái khám',
            default => 'Không xác định',
        };
    }
}
