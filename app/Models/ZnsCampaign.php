<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ZnsCampaign extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

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

            if (is_numeric($campaign->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $campaign->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác campaign ZNS ở chi nhánh này.',
                );
            }

            if ($campaign->status === static::STATUS_SCHEDULED && blank($campaign->scheduled_at)) {
                $campaign->scheduled_at = now()->addMinutes(5);
            }

            if ($campaign->status === static::STATUS_RUNNING && blank($campaign->started_at)) {
                $campaign->started_at = now();
            }

            if (
                in_array($campaign->status, [static::STATUS_COMPLETED, static::STATUS_FAILED, static::STATUS_CANCELLED], true)
                && blank($campaign->finished_at)
            ) {
                $campaign->finished_at = now();
            }

            if ($campaign->exists && $campaign->isDirty('status')) {
                $fromStatus = (string) ($campaign->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $campaign->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf('Không thể chuyển campaign từ "%s" sang "%s".', $fromStatus, $toStatus),
                    ]);
                }
            }
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

    protected static function canTransitionStatus(string $fromStatus, string $toStatus): bool
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
}
