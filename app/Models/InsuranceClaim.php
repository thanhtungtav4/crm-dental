<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InsuranceClaim extends Model
{
    use HasFactory;

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
                $latestCode = self::query()
                    ->where('claim_number', 'like', $prefix.'%')
                    ->orderByDesc('id')
                    ->value('claim_number');

                $sequence = 1;
                if (is_string($latestCode) && preg_match('/(\d{4})$/', $latestCode, $matches) === 1) {
                    $sequence = ((int) $matches[1]) + 1;
                }

                $claim->claim_number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }

            if ($claim->exists && $claim->isDirty('status')) {
                ActionGate::authorize(
                    ActionPermission::INSURANCE_CLAIM_DECISION,
                    'Bạn không có quyền phê duyệt/từ chối hồ sơ bảo hiểm.',
                );

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

    public function submit(): void
    {
        $this->status = self::STATUS_SUBMITTED;
        $this->save();
    }

    public function approve(?float $amountApproved = null): void
    {
        if ($amountApproved !== null) {
            $this->amount_approved = max(0, round($amountApproved, 2));
        }

        if ((float) $this->amount_approved <= 0) {
            $this->amount_approved = (float) $this->amount_claimed;
        }

        $this->status = self::STATUS_APPROVED;
        $this->save();
    }

    public function deny(string $reasonCode, ?string $note = null): void
    {
        $this->denial_reason_code = trim($reasonCode);
        $this->denial_note = $note;
        $this->status = self::STATUS_DENIED;
        $this->save();
    }

    public function resubmit(): void
    {
        $this->status = self::STATUS_RESUBMITTED;
        $this->save();
    }

    public function markPaid(?float $amount = null, string $method = 'transfer', ?string $note = null): Payment
    {
        if (! in_array($this->status, [self::STATUS_APPROVED, self::STATUS_RESUBMITTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ hồ sơ bảo hiểm đã duyệt mới được ghi nhận đã thanh toán.',
            ]);
        }

        $invoice = $this->invoice;

        if (! $invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Không tìm thấy hóa đơn gắn với hồ sơ bảo hiểm.',
            ]);
        }

        $paymentAmount = $amount !== null
            ? max(0, round($amount, 2))
            : max(0, (float) ($this->amount_approved ?: $this->amount_claimed));

        $payment = $invoice->recordPayment(
            amount: $paymentAmount,
            method: $method,
            notes: $note,
            paidAt: now(),
            direction: 'receipt',
            refundReason: null,
            transactionRef: 'CLAIM-'.$this->id.'-PAID',
            paymentSource: 'insurance',
            insuranceClaimNumber: $this->claim_number,
            receivedBy: auth()->id(),
            reversalOfId: null,
            isDeposit: false,
        );

        $this->status = self::STATUS_PAID;
        $this->save();

        return $payment;
    }
}
