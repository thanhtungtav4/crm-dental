<?php

namespace App\Models;

use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class PopupAnnouncement extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const PRIORITY_INFO = 'info';

    public const PRIORITY_SUCCESS = 'success';

    public const PRIORITY_WARNING = 'warning';

    public const PRIORITY_DANGER = 'danger';

    protected const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SCHEDULED, self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        self::STATUS_SCHEDULED => [self::STATUS_PUBLISHED, self::STATUS_CANCELLED],
        self::STATUS_PUBLISHED => [self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    protected $fillable = [
        'code',
        'title',
        'message',
        'priority',
        'status',
        'target_role_names',
        'target_branch_ids',
        'require_ack',
        'show_once',
        'starts_at',
        'ends_at',
        'published_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_role_names' => 'array',
            'target_branch_ids' => 'array',
            'require_ack' => 'boolean',
            'show_once' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $announcement): void {
            if (blank($announcement->code)) {
                $announcement->code = static::generateCode();
            }

            if (blank($announcement->created_by) && auth()->check()) {
                $announcement->created_by = auth()->id();
            }
        });

        static::saving(function (self $announcement): void {
            if (auth()->check()) {
                $announcement->updated_by = auth()->id();
            }

            $announcement->show_once = true;
            $announcement->message = static::normalizeMessage($announcement->message);
            $announcement->target_role_names = static::normalizeRoleNames($announcement->target_role_names);
            $announcement->target_branch_ids = static::normalizeBranchIds($announcement->target_branch_ids);

            if ($announcement->target_branch_ids !== []) {
                foreach ($announcement->target_branch_ids as $branchId) {
                    BranchAccess::assertCanAccessBranch(
                        branchId: (int) $branchId,
                        field: 'target_branch_ids',
                        message: 'Bạn không có quyền gửi popup tới chi nhánh này.',
                    );
                }
            }

            if ($announcement->exists && $announcement->isDirty('status')) {
                $fromStatus = (string) ($announcement->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $announcement->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf('Không thể chuyển popup từ "%s" sang "%s".', $fromStatus, $toStatus),
                    ]);
                }
            }

            if ($announcement->status === static::STATUS_SCHEDULED && blank($announcement->starts_at)) {
                $announcement->starts_at = now()->addMinute();
            }

            if ($announcement->status === static::STATUS_PUBLISHED && blank($announcement->published_at)) {
                $announcement->published_at = now();
            }

            if (filled($announcement->starts_at) && filled($announcement->ends_at) && $announcement->ends_at->lt($announcement->starts_at)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
                ]);
            }

            if ($announcement->status !== static::STATUS_DRAFT && $announcement->target_role_names === []) {
                throw ValidationException::withMessages([
                    'target_role_names' => 'Vui lòng chọn ít nhất một nhóm quyền nhận popup.',
                ]);
            }

            if ($announcement->status !== static::STATUS_DRAFT && $announcement->target_branch_ids === []) {
                $authUser = auth()->user();
                $isAllowedGlobalSender = ! $authUser instanceof User
                    || (
                        collect(ClinicRuntimeSettings::popupAnnouncementSenderRoles())->isNotEmpty()
                        && $authUser->hasAnyRole(ClinicRuntimeSettings::popupAnnouncementSenderRoles())
                    );

                if (! $isAllowedGlobalSender) {
                    throw ValidationException::withMessages([
                        'target_branch_ids' => 'Chỉ admin tổng hoặc admin chi nhánh mới được gửi popup toàn hệ thống.',
                    ]);
                }
            }
        });
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
        return $this->hasMany(PopupAnnouncementDelivery::class);
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

        return $query->where(function (Builder $builder) use ($branchIds): void {
            $builder
                ->whereNull('target_branch_ids')
                ->orWhereJsonLength('target_branch_ids', 0);

            foreach ($branchIds as $branchId) {
                $builder->orWhereJsonContains('target_branch_ids', (int) $branchId);
            }
        });
    }

    public function isDueForDispatch(): bool
    {
        if ($this->status === static::STATUS_CANCELLED || $this->status === static::STATUS_EXPIRED) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return in_array($this->status, [static::STATUS_SCHEDULED, static::STATUS_PUBLISHED], true);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            static::STATUS_DRAFT => 'Nháp',
            static::STATUS_SCHEDULED => 'Đã lên lịch',
            static::STATUS_PUBLISHED => 'Đang phát',
            static::STATUS_CANCELLED => 'Đã hủy',
            static::STATUS_EXPIRED => 'Hết hiệu lực',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function priorityOptions(): array
    {
        return [
            static::PRIORITY_INFO => 'Thông tin',
            static::PRIORITY_SUCCESS => 'Thành công',
            static::PRIORITY_WARNING => 'Cảnh báo',
            static::PRIORITY_DANGER => 'Khẩn cấp',
        ];
    }

    public static function generateCode(): string
    {
        $prefix = now()->format('YmdHis');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return "POP-{$prefix}-{$random}";
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

    protected static function normalizeMessage(mixed $message): string
    {
        return trim((string) $message);
    }

    /**
     * @param  array<int, string>|string|null  $roleNames
     * @return array<int, string>
     */
    protected static function normalizeRoleNames(array|string|null $roleNames): array
    {
        $values = is_array($roleNames)
            ? $roleNames
            : explode(',', (string) $roleNames);

        return collect($values)
            ->filter(static fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(static fn (string $item): string => trim($item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>|int|string|null  $branchIds
     * @return array<int, int>
     */
    protected static function normalizeBranchIds(array|int|string|null $branchIds): array
    {
        if (is_array($branchIds)) {
            $values = $branchIds;
        } elseif ($branchIds === null || $branchIds === '') {
            $values = [];
        } else {
            $values = explode(',', (string) $branchIds);
        }

        return collect($values)
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();
    }
}
