<?php

namespace App\Models;

use App\Services\ZnsCampaignWorkflowService;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ZnsCampaign extends Model
{
    use SoftDeletes;

    protected static int $managedWorkflowDepth = 0;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PROCESSING_LOCK_TTL_MINUTES = 15;

    protected const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SCHEDULED, self::STATUS_RUNNING, self::STATUS_CANCELLED],
        self::STATUS_SCHEDULED => [self::STATUS_RUNNING, self::STATUS_CANCELLED],
        self::STATUS_RUNNING => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_FAILED => [self::STATUS_RUNNING, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'code',
        'name',
        'branch_id',
        'audience_source',
        'audience_last_visit_before_days',
        'template_key',
        'template_id',
        'audience_payload',
        'message_payload',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'sent_count',
        'failed_count',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'audience_last_visit_before_days' => 'integer',
            'audience_payload' => 'array',
            'message_payload' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'locked_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (blank($campaign->code)) {
                $campaign->code = static::generateCode();
            }

            if (blank($campaign->created_by) && auth()->check()) {
                $campaign->created_by = auth()->id();
            }
        });

        static::saving(function (self $campaign): void {
            if (auth()->check()) {
                $campaign->updated_by = auth()->id();
            }

            $authUser = BranchAccess::currentUser();

            if ($authUser instanceof User && ! $authUser->hasRole('Admin')) {
                BranchAccess::assertCanAccessBranch(
                    branchId: $campaign->branch_id !== null ? (int) $campaign->branch_id : null,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác campaign ZNS ở chi nhánh này.',
                );
            }

            $campaign->status = static::normalizeStatusValue($campaign->status) ?? static::STATUS_DRAFT;

            if (! $campaign->exists || ! $campaign->isDirty('status')) {
                static::assertWorkflowControlledFields($campaign);

                return;
            }

            if (! static::isManagedWorkflow()) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai campaign ZNS chi duoc thay doi qua ZnsCampaignWorkflowService.',
                ]);
            }

            $fromStatus = static::normalizeStatusValue($campaign->getOriginal('status')) ?? static::STATUS_DRAFT;
            $toStatus = static::normalizeStatusValue($campaign->status) ?? static::STATUS_DRAFT;

            if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Khong the chuyen campaign ZNS tu "%s" sang "%s".',
                        static::statusLabel($fromStatus),
                        static::statusLabel($toStatus),
                    ),
                ]);
            }

            static::assertWorkflowControlledFields($campaign);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign ZNS khong ho tro xoa. Vui long huy campaign qua workflow.',
            ]);
        });

        static::restoring(function (): void {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign ZNS khong ho tro khoi phuc tu thao tac xoa.',
            ]);
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ZnsCampaignDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            static::STATUS_DRAFT => 'Nháp',
            static::STATUS_SCHEDULED => 'Đã lên lịch',
            static::STATUS_RUNNING => 'Đang chạy',
            static::STATUS_COMPLETED => 'Hoàn tất',
            static::STATUS_FAILED => 'Thất bại',
            static::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        $normalizedStatus = static::normalizeStatusValue($status);

        return static::statusOptions()[$normalizedStatus] ?? (string) $status;
    }

    public static function normalizeStatusValue(mixed $status): ?string
    {
        $normalized = strtolower(trim((string) $status));

        return array_key_exists($normalized, static::statusOptions()) ? $normalized : null;
    }

    public static function runWithinManagedWorkflow(callable $callback): mixed
    {
        static::$managedWorkflowDepth++;

        try {
            return $callback();
        } finally {
            static::$managedWorkflowDepth = max(0, static::$managedWorkflowDepth - 1);
        }
    }

    public function cancel(?string $reason = null, ?int $actorId = null): self
    {
        return app(ZnsCampaignWorkflowService::class)->cancel($this, $reason, $actorId);
    }

    public static function generateCode(): string
    {
        $prefix = now()->format('YmdHis');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return "ZNS-{$prefix}-{$random}";
    }

    public function scopeBranchAccessible(Builder $query): Builder
    {
        $authUser = auth()->user();

        if (! $authUser instanceof User || $authUser->hasRole('Admin')) {
            return $query;
        }

        $branchIds = $authUser->accessibleBranchIds();
        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('branch_id', $branchIds);
    }

    public function scopeRunnerClaimable(
        Builder $query,
        ?CarbonInterface $referenceTime = null,
        int $ttlMinutes = self::PROCESSING_LOCK_TTL_MINUTES,
    ): Builder {
        $lockedBefore = ($referenceTime ?? now())->copy()->subMinutes($ttlMinutes);

        return $query->where(function (Builder $lockQuery) use ($lockedBefore): void {
            $lockQuery->whereNull('processing_token')
                ->orWhereNull('locked_at')
                ->orWhere('locked_at', '<=', $lockedBefore);
        });
    }

    public static function canAccessModule(?User $authUser): bool
    {
        if (! $authUser instanceof User) {
            return false;
        }

        if ($authUser->hasRole('Admin')) {
            return true;
        }

        return $authUser->hasRole('Manager') && $authUser->hasAnyAccessibleBranch();
    }

    public function isVisibleTo(User $authUser): bool
    {
        if (! static::canAccessModule($authUser)) {
            return false;
        }

        if ($authUser->hasRole('Admin')) {
            return true;
        }

        if ($this->branch_id === null) {
            return $authUser->hasAnyAccessibleBranch();
        }

        return in_array((int) $this->branch_id, $authUser->accessibleBranchIds(), true);
    }

    public static function canTransitionStatus(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array(
            $toStatus,
            static::STATUS_TRANSITIONS[$fromStatus] ?? [],
            true,
        );
    }

    protected static function isManagedWorkflow(): bool
    {
        return static::$managedWorkflowDepth > 0;
    }

    public function hasActiveProcessingLock(
        ?CarbonInterface $referenceTime = null,
        int $ttlMinutes = self::PROCESSING_LOCK_TTL_MINUTES,
    ): bool {
        if (blank($this->processing_token) || ! $this->locked_at instanceof CarbonInterface) {
            return false;
        }

        return $this->locked_at->gt(($referenceTime ?? now())->copy()->subMinutes($ttlMinutes));
    }

    protected static function assertWorkflowControlledFields(self $campaign): void
    {
        if (static::isManagedWorkflow()) {
            return;
        }

        foreach (['scheduled_at', 'started_at', 'finished_at', 'sent_count', 'failed_count'] as $field) {
            if ($campaign->exists && $campaign->isDirty($field)) {
                throw ValidationException::withMessages([
                    $field => 'Workflow campaign ZNS chi duoc cap nhat qua ZnsCampaignWorkflowService.',
                ]);
            }
        }
    }
}
