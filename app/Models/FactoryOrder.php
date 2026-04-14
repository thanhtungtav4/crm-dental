<?php

namespace App\Models;

use App\Services\DoctorBranchAssignmentService;
use App\Services\FactoryOrderNumberGenerator;
use App\Services\FactoryOrderWorkflowService;
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

    protected static bool $allowsManagedWorkflowMutation = false;

    protected $fillable = [
        'order_no',
        'patient_id',
        'branch_id',
        'doctor_id',
        'supplier_id',
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
            'supplier_id' => 'integer',
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
            $supplier = is_numeric($order->supplier_id)
                ? Supplier::query()
                    ->select(['id', 'name', 'active'])
                    ->find((int) $order->supplier_id)
                : null;

            if (! $supplier instanceof Supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Vui lòng chọn nhà cung cấp cho lệnh labo.',
                ]);
            }

            if (! $supplier->active && (! $order->exists || $order->isDirty('supplier_id'))) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Chỉ được chọn nhà cung cấp đang hoạt động.',
                ]);
            }

            $order->vendor_name = trim((string) $supplier->name);

            $patientBranchId = Patient::query()
                ->whereKey((int) $order->patient_id)
                ->value('first_branch_id');

            if (blank($order->branch_id)) {
                $order->branch_id = $patientBranchId;
            }

            if ($patientBranchId === null) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Bệnh nhân chưa có chi nhánh gốc để tạo lệnh labo.',
                ]);
            }

            if ((int) $order->branch_id !== (int) $patientBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Chi nhánh của lệnh labo phải trùng với chi nhánh gốc của bệnh nhân.',
                ]);
            }

            if (is_numeric($order->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $order->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác lệnh labo ở chi nhánh này.',
                );
            }

            if (is_numeric($order->doctor_id) && is_numeric($order->branch_id)) {
                app(DoctorBranchAssignmentService::class)->ensureDoctorCanWorkAtBranch(
                    doctorId: (int) $order->doctor_id,
                    branchId: (int) $order->branch_id,
                );
            }

            if ($order->exists && $order->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'Trang thai lenh labo chi duoc thay doi qua FactoryOrderWorkflowService.',
                    ]);
                }

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

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'factory_order' => 'Lệnh labo không hỗ trợ xóa trực tiếp. Vui lòng hủy lệnh qua workflow.',
            ]);
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
        return app(FactoryOrderNumberGenerator::class)->next();
    }

    public function cancel(?string $reason = null): self
    {
        return app(FactoryOrderWorkflowService::class)->cancel($this, $reason);
    }

    public function markOrdered(): self
    {
        return app(FactoryOrderWorkflowService::class)->markOrdered($this);
    }

    public function markInProgress(): self
    {
        return app(FactoryOrderWorkflowService::class)->markInProgress($this);
    }

    public function markDelivered(): self
    {
        return app(FactoryOrderWorkflowService::class)->markDelivered($this);
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

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! is_numeric($this->branch_id)) {
            return false;
        }

        return in_array((int) $this->branch_id, $user->accessibleBranchIds(), true);
    }

    public static function runWithinManagedWorkflow(callable $callback): mixed
    {
        $previousState = static::$allowsManagedWorkflowMutation;
        static::$allowsManagedWorkflowMutation = true;

        try {
            return $callback();
        } finally {
            static::$allowsManagedWorkflowMutation = $previousState;
        }
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canMutateItems(): bool
    {
        return $this->isEditable();
    }

    public function assertItemsEditable(string $field = 'factory_order_id'): void
    {
        if ($this->canMutateItems()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Chi co the sua hang muc labo khi lenh dang o trang thai nhap.',
        ]);
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
}
