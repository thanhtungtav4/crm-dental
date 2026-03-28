<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchTransferRequest extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chờ xử lý',
        self::STATUS_APPLIED => 'Đã áp dụng',
        self::STATUS_REJECTED => 'Đã từ chối',
        self::STATUS_CANCELLED => 'Đã hủy',
    ];

    protected const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [
            self::STATUS_PENDING,
            self::STATUS_APPLIED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_APPLIED => [self::STATUS_APPLIED],
        self::STATUS_REJECTED => [self::STATUS_REJECTED],
        self::STATUS_CANCELLED => [self::STATUS_CANCELLED],
    ];

    protected $fillable = [
        'patient_id',
        'from_branch_id',
        'to_branch_id',
        'status',
        'requested_by',
        'decided_by',
        'requested_at',
        'decided_at',
        'applied_at',
        'reason',
        'note',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $request): void {
            $request->status = strtolower(trim((string) ($request->status ?: self::STATUS_PENDING)));

            if (! $request->exists || ! $request->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'Trang thai yeu cau chuyen chi nhanh chi duoc thay doi qua PatientBranchTransferService.',
                ]);
            }

            $fromStatus = strtolower(trim((string) ($request->getOriginal('status') ?: self::STATUS_PENDING)));

            if (! static::canTransition($fromStatus, $request->status)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'status' => 'BRANCH_TRANSFER_STATE_INVALID: Không thể chuyển trạng thái yêu cầu chuyển chi nhánh.',
                ]);
            }
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, static::STATUS_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function statusLabel(?string $status): string
    {
        return static::STATUS_LABELS[$status ?? self::STATUS_PENDING] ?? 'Không xác định';
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
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
