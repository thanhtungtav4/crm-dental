<?php

namespace App\Models;

use App\Services\ReceiptExpenseWorkflowService;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ReceiptExpense extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'receipts_expense';

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'invoice_id',
        'voucher_code',
        'voucher_type',
        'voucher_date',
        'group_code',
        'category_code',
        'amount',
        'payment_method',
        'payer_or_receiver',
        'content',
        'status',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'invoice_id' => 'integer',
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $receiptExpense): void {
            $invoiceBranchId = null;

            if (is_numeric($receiptExpense->invoice_id)) {
                $invoiceBranchId = Invoice::query()
                    ->whereKey((int) $receiptExpense->invoice_id)
                    ->value('branch_id');

                BranchAccess::assertCanAccessBranch(
                    branchId: is_numeric($invoiceBranchId) ? (int) $invoiceBranchId : null,
                    field: 'invoice_id',
                    message: 'Bạn không có quyền gắn hóa đơn ngoài phạm vi chi nhánh được phân quyền.',
                );
            }

            $patientBranchId = null;

            if (is_numeric($receiptExpense->patient_id)) {
                $patientBranchId = Patient::query()
                    ->whereKey((int) $receiptExpense->patient_id)
                    ->value('first_branch_id');

                BranchAccess::assertCanAccessBranch(
                    branchId: is_numeric($patientBranchId) ? (int) $patientBranchId : null,
                    field: 'patient_id',
                    message: 'Bạn không có quyền gắn bệnh nhân ngoài phạm vi chi nhánh được phân quyền.',
                );
            }

            $resolvedBranchId = $receiptExpense->resolveBranchId();

            static::assertConsistentLinkedBranch(
                field: 'invoice_id',
                resolvedBranchId: $resolvedBranchId,
                linkedBranchId: $invoiceBranchId,
                message: 'Chi nhánh phiếu thu/chi phải khớp với chi nhánh của hóa đơn liên quan.',
            );

            static::assertConsistentLinkedBranch(
                field: 'patient_id',
                resolvedBranchId: $resolvedBranchId,
                linkedBranchId: $patientBranchId,
                message: 'Chi nhánh phiếu thu/chi phải khớp với chi nhánh hồ sơ bệnh nhân.',
            );

            BranchAccess::assertCanAccessBranch(
                branchId: $resolvedBranchId,
                field: 'clinic_id',
                message: 'Bạn không có quyền thao tác phiếu thu/chi ở chi nhánh này.',
            );

            if ($receiptExpense->exists && $receiptExpense->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'Trang thai phieu thu chi chi duoc thay doi qua ReceiptExpenseWorkflowService.',
                    ]);
                }

                $fromStatus = (string) ($receiptExpense->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $receiptExpense->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf(
                            'Khong the chuyen phieu thu chi tu "%s" sang "%s".',
                            static::statusLabel($fromStatus),
                            static::statusLabel($toStatus),
                        ),
                    ]);
                }
            }

            $receiptExpense->clinic_id = $resolvedBranchId;
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'receipt_expense' => 'Phiếu thu/chi không hỗ trợ xóa trực tiếp. Vui lòng quản lý trạng thái qua workflow.',
            ]);
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'clinic_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->clinic_id ?? $this->invoice?->branch_id ?? $this->patient?->first_branch_id;

        return is_numeric($branchId) ? (int) $branchId : null;
    }

    public function getVoucherTypeLabel(): string
    {
        return match ($this->voucher_type) {
            'expense' => 'Phiếu chi',
            default => 'Phiếu thu',
        };
    }

    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'card' => 'Thẻ',
            default => 'Khác',
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public function approve(?string $reason = null, ?int $actorId = null): self
    {
        return app(ReceiptExpenseWorkflowService::class)->approve($this, $reason, $actorId);
    }

    public function post(?string $reason = null, ?int $actorId = null): self
    {
        return app(ReceiptExpenseWorkflowService::class)->post($this, $reason, $actorId);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED => 'Đã duyệt',
            self::STATUS_POSTED => 'Đã hạch toán',
            self::STATUS_CANCELLED => 'Đã hủy',
            default => 'Nháp',
        };
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

    protected static function canTransitionStatus(string $fromStatus, string $toStatus): bool
    {
        $allowedTransitions = match ($fromStatus) {
            self::STATUS_DRAFT => [self::STATUS_APPROVED, self::STATUS_POSTED],
            self::STATUS_APPROVED => [self::STATUS_POSTED],
            self::STATUS_POSTED => [self::STATUS_POSTED],
            self::STATUS_CANCELLED => [self::STATUS_CANCELLED],
            default => [],
        };

        return in_array($toStatus, $allowedTransitions, true);
    }

    protected static function assertConsistentLinkedBranch(
        string $field,
        ?int $resolvedBranchId,
        mixed $linkedBranchId,
        string $message,
    ): void {
        if (! is_numeric($linkedBranchId) || $resolvedBranchId === null) {
            return;
        }

        if ((int) $linkedBranchId === $resolvedBranchId) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}
