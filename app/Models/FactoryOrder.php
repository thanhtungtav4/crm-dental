<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class FactoryOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ORDERED = 'ordered';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_ORDERED, self::STATUS_CANCELLED],
        self::STATUS_ORDERED => [self::STATUS_IN_PROGRESS, self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'order_no',
        'patient_id',
        'branch_id',
        'doctor_id',
        'requested_by',
        'status',
        'priority',
        'vendor_name',
        'ordered_at',
        'due_at',
        'delivered_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'branch_id' => 'integer',
            'doctor_id' => 'integer',
            'requested_by' => 'integer',
            'ordered_at' => 'datetime',
            'due_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (blank($order->order_no)) {
                $order->order_no = static::generateOrderNo();
            }

            if (blank($order->requested_by) && auth()->check()) {
                $order->requested_by = auth()->id();
            }
        });

        static::saving(function (self $order): void {
            $patientBranchId = Patient::query()
                ->whereKey((int) $order->patient_id)
                ->value('first_branch_id');

            if (blank($order->branch_id)) {
                $order->branch_id = $patientBranchId;
            }

            if (is_numeric($order->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $order->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác lệnh labo ở chi nhánh này.',
                );
            }

            if ($order->exists && $order->isDirty('status')) {
                $fromStatus = (string) ($order->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $order->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf('Không thể chuyển trạng thái lệnh labo từ "%s" sang "%s".', $fromStatus, $toStatus),
                    ]);
                }
            }

            if ($order->status === static::STATUS_DELIVERED && blank($order->delivered_at)) {
                $order->delivered_at = now();
            }

            if ($order->status === static::STATUS_CANCELLED && blank($order->notes)) {
                throw ValidationException::withMessages([
                    'notes' => 'Vui lòng ghi chú lý do hủy lệnh labo.',
                ]);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FactoryOrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            static::STATUS_DRAFT => 'Nháp',
            static::STATUS_ORDERED => 'Đã đặt',
            static::STATUS_IN_PROGRESS => 'Đang làm',
            static::STATUS_DELIVERED => 'Đã giao',
            static::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    public static function generateOrderNo(): string
    {
        $date = now()->format('Ymd');
        $lastOrderNo = static::query()
            ->whereDate('created_at', today())
            ->where('order_no', 'like', "LAB-{$date}-%")
            ->orderByDesc('id')
            ->value('order_no');

        $lastSequence = 0;
        if (is_string($lastOrderNo) && preg_match('/LAB-\d{8}-(\d{4})$/', $lastOrderNo, $matches) === 1) {
            $lastSequence = (int) ($matches[1] ?? 0);
        }

        return sprintf('LAB-%s-%04d', $date, $lastSequence + 1);
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
