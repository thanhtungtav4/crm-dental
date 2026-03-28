<?php

namespace App\Models;

use App\Casts\NullableEncrypted;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Consent extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PENDING, self::STATUS_SIGNED, self::STATUS_EXPIRED],
        self::STATUS_SIGNED => [self::STATUS_SIGNED, self::STATUS_REVOKED, self::STATUS_EXPIRED],
        self::STATUS_REVOKED => [self::STATUS_REVOKED],
        self::STATUS_EXPIRED => [self::STATUS_EXPIRED],
    ];

    protected $fillable = [
        'patient_id',
        'branch_id',
        'service_id',
        'plan_item_id',
        'consent_type',
        'consent_version',
        'status',
        'signed_by',
        'signed_at',
        'expires_at',
        'revoked_at',
        'note',
        'signature_context',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'note' => NullableEncrypted::class,
            'signature_context' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $consent): void {
            if (blank($consent->branch_id)) {
                $consent->branch_id = static::inferBranchId($consent);
            }

            $consent->status = strtolower(trim((string) ($consent->status ?: self::STATUS_PENDING)));

            if ($consent->exists && $consent->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'CONSENT_STATE_INVALID: Trang thai consent chi duoc thay doi qua ConsentLifecycleService.',
                    ]);
                }

                $fromStatus = strtolower(trim((string) ($consent->getOriginal('status') ?: self::STATUS_PENDING)));

                if (! self::canTransition($fromStatus, $consent->status)) {
                    throw ValidationException::withMessages([
                        'status' => 'CONSENT_STATE_INVALID: Không thể chuyển trạng thái consent không hợp lệ.',
                    ]);
                }
            }

            if ($consent->exists && $consent->hasLockedContentMutations()) {
                throw ValidationException::withMessages([
                    'consent' => 'CONSENT_CONTENT_LOCKED: Consent đã ký hoặc đã kết thúc vòng đời nên không thể sửa nội dung.',
                ]);
            }

            if ($consent->status === self::STATUS_SIGNED) {
                if (! $consent->signed_by) {
                    throw ValidationException::withMessages([
                        'signed_by' => 'Consent đã ký cần người ký xác nhận.',
                    ]);
                }

                $consent->signed_at = $consent->signed_at ?? now();
            }

            if ($consent->status === self::STATUS_REVOKED) {
                $consent->revoked_at = $consent->revoked_at ?? now();
            }
        });
    }

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function scopeSigned($query)
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    public function scopeValidAt($query, Carbon $moment)
    {
        return $query
            ->where('status', self::STATUS_SIGNED)
            ->where(function ($innerQuery) use ($moment): void {
                $innerQuery->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $moment);
            });
    }

    public function isValidAt(Carbon $moment): bool
    {
        if ($this->status !== self::STATUS_SIGNED) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return Carbon::parse($this->expires_at)->gte($moment);
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->branch_id
            ?? $this->planItem?->treatmentPlan?->branch_id
            ?? $this->patient?->first_branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }

    protected static function inferBranchId(self $consent): ?int
    {
        if ($consent->plan_item_id) {
            $planBranchId = PlanItem::query()
                ->join('treatment_plans', 'treatment_plans.id', '=', 'plan_items.treatment_plan_id')
                ->where('plan_items.id', (int) $consent->plan_item_id)
                ->value('treatment_plans.branch_id');

            if ($planBranchId !== null) {
                return (int) $planBranchId;
            }
        }

        if (! $consent->patient_id) {
            return null;
        }

        $patientBranchId = Patient::query()
            ->whereKey((int) $consent->patient_id)
            ->value('first_branch_id');

        return $patientBranchId !== null ? (int) $patientBranchId : null;
    }

    protected function hasLockedContentMutations(): bool
    {
        $originalStatus = strtolower(trim((string) ($this->getOriginal('status') ?: self::STATUS_PENDING)));

        if (! in_array($originalStatus, [self::STATUS_SIGNED, self::STATUS_REVOKED, self::STATUS_EXPIRED], true)) {
            return false;
        }

        $allowedDirtyFields = ['status', 'revoked_at', 'updated_at'];
        $dirtyFields = array_keys($this->getDirty());

        foreach ($dirtyFields as $field) {
            if (! in_array($field, $allowedDirtyFields, true)) {
                return true;
            }
        }

        return false;
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
