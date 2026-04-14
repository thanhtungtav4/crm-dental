<?php

namespace App\Models;

use App\Services\InsuranceClaimWorkflowService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class InsuranceClaim extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_RESUBMITTED = 'resubmitted';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_DENIED => [self::STATUS_RESUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_RESUBMITTED => [self::STATUS_APPROVED, self::STATUS_DENIED, self::STATUS_CANCELLED],
        self::STATUS_PAID => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'invoice_id',
        'patient_id',
        'claim_number',
        'payer_name',
        'amount_claimed',
        'amount_approved',
        'status',
        'denial_reason_code',
        'denial_note',
        'submitted_at',
        'approved_at',
        'denied_at',
        'paid_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_claimed' => 'decimal:2',
            'amount_approved' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $claim): void {
            $claim->status = strtolower(trim((string) $claim->status));

            if (blank($claim->claim_number)) {
                $prefix = 'CLM-'.now()->format('Ymd').'-';
                $claim->claim_number = Cache::lock("insurance_claim:{$prefix}", 5)
                    ->block(5, function () use ($prefix): string {
                        $latestCode = self::query()
                            ->where('claim_number', 'like', $prefix.'%')
                            ->orderByDesc('claim_number')
                            ->value('claim_number');

                        $sequence = 1;
                        if (is_string($latestCode) && preg_match('/(\d{4})$/', $latestCode, $matches) === 1) {
                            $sequence = ((int) $matches[1]) + 1;
                        }

                        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                    });
            }

            if ($claim->exists && $claim->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'INSURANCE_CLAIM_STATE_INVALID: Trang thai ho so bao hiem chi duoc thay doi qua InsuranceClaimWorkflowService.',
                    ]);
                }

                $fromStatus = (string) $claim->getOriginal('status');
                $toStatus = (string) $claim->status;

                if (! self::canTransition($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => 'INSURANCE_CLAIM_STATE_INVALID: Không thể chuyển trạng thái hồ sơ bảo hiểm.',
                    ]);
                }
            }

            if (in_array($claim->status, [self::STATUS_SUBMITTED, self::STATUS_RESUBMITTED], true)) {
                $claim->submitted_at = $claim->submitted_at ?? now();
            }

            if ($claim->status === self::STATUS_APPROVED) {
                $claim->approved_at = $claim->approved_at ?? now();
            }

            if ($claim->status === self::STATUS_DENIED) {
                if (blank($claim->denial_reason_code)) {
                    throw ValidationException::withMessages([
                        'denial_reason_code' => 'Vui lòng nhập mã lý do từ chối bảo hiểm.',
                    ]);
                }

                $claim->denied_at = $claim->denied_at ?? now();
            }

            if ($claim->status !== self::STATUS_DENIED) {
                $claim->denial_reason_code = null;
                $claim->denial_note = null;
            }

            if ($claim->status === self::STATUS_PAID) {
                $claim->paid_at = $claim->paid_at ?? now();
            }

            if ($claim->status === self::STATUS_CANCELLED) {
                $claim->cancelled_at = $claim->cancelled_at ?? now();
            }
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'insurance_claim' => 'Hồ sơ bảo hiểm không hỗ trợ xóa trực tiếp. Vui lòng hủy hồ sơ qua workflow.',
            ]);
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    public function submit(?string $reason = null, ?int $actorId = null): void
    {
        app(InsuranceClaimWorkflowService::class)->submit($this, $reason, $actorId);
    }

    public function approve(?float $amountApproved = null, ?string $reason = null, ?int $actorId = null): void
    {
        app(InsuranceClaimWorkflowService::class)->approve($this, $amountApproved, $reason, $actorId);
    }

    public function deny(string $reasonCode, ?string $note = null, ?string $reason = null, ?int $actorId = null): void
    {
        app(InsuranceClaimWorkflowService::class)->deny($this, $reasonCode, $note, $reason, $actorId);
    }

    public function resubmit(?string $reason = null, ?int $actorId = null): void
    {
        app(InsuranceClaimWorkflowService::class)->resubmit($this, $reason, $actorId);
    }

    public function cancel(?string $reason = null, ?int $actorId = null): void
    {
        app(InsuranceClaimWorkflowService::class)->cancel($this, $reason, $actorId);
    }

    public function markPaid(
        ?float $amount = null,
        string $method = 'transfer',
        ?string $note = null,
        ?string $reason = null,
        ?int $actorId = null,
    ): Payment {
        return app(InsuranceClaimWorkflowService::class)->markPaid($this, $amount, $method, $note, $reason, $actorId);
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
