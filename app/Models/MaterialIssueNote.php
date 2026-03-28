<?php

namespace App\Models;

use App\Services\MaterialIssueNoteWorkflowService;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class MaterialIssueNote extends Model
{
    use SoftDeletes;

    protected static bool $allowsManagedWorkflowMutation = false;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    protected const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_POSTED, self::STATUS_CANCELLED],
        self::STATUS_POSTED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'note_no',
        'patient_id',
        'branch_id',
        'issued_by',
        'issued_at',
        'status',
        'reason',
        'notes',
        'posted_at',
        'posted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'branch_id' => 'integer',
            'issued_by' => 'integer',
            'issued_at' => 'datetime',
            'posted_at' => 'datetime',
            'posted_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (blank($note->note_no)) {
                $note->note_no = static::generateNoteNo();
            }

            if (blank($note->issued_at)) {
                $note->issued_at = now();
            }

            if (blank($note->issued_by) && auth()->check()) {
                $note->issued_by = auth()->id();
            }
        });

        static::saving(function (self $note): void {
            $patientBranchId = null;

            if (is_numeric($note->patient_id)) {
                $patientBranchId = Patient::query()
                    ->whereKey((int) $note->patient_id)
                    ->value('first_branch_id');

                BranchAccess::assertCanAccessBranch(
                    branchId: is_numeric($patientBranchId) ? (int) $patientBranchId : null,
                    field: 'patient_id',
                    message: 'Bạn không có quyền gắn bệnh nhân ngoài phạm vi chi nhánh được phân quyền.',
                );
            }

            if (blank($note->branch_id) && $note->patient_id) {
                $note->branch_id = $patientBranchId;
            }

            if (is_numeric($note->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $note->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác phiếu xuất ở chi nhánh này.',
                );
            }

            if ($note->exists && $note->isDirty('status')) {
                if (! static::$allowsManagedWorkflowMutation) {
                    throw ValidationException::withMessages([
                        'status' => 'Trang thai phieu xuat chi duoc thay doi qua MaterialIssueNoteWorkflowService.',
                    ]);
                }

                $fromStatus = (string) ($note->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $note->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf(
                            'Không thể chuyển trạng thái phiếu xuất từ "%s" sang "%s".',
                            static::statusLabel($fromStatus),
                            static::statusLabel($toStatus),
                        ),
                    ]);
                }
            }

            if (
                $note->exists
                && in_array((string) ($note->getOriginal('status') ?? static::STATUS_DRAFT), [
                    static::STATUS_POSTED,
                    static::STATUS_CANCELLED,
                ], true)
                && $note->isDirty()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu đã chốt không thể chỉnh sửa.',
                ]);
            }
        });

        static::deleting(function (self $note): void {
            if ($note->status !== static::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => $note->status === static::STATUS_POSTED
                        ? 'Phiếu đã xuất kho không thể xóa.'
                        : 'Chỉ phiếu nháp mới có thể xóa.',
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

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialIssueItem::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            static::STATUS_DRAFT => 'Nháp',
            static::STATUS_POSTED => 'Đã xuất kho',
            static::STATUS_CANCELLED => 'Đã hủy',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusOptions()[$status ?? static::STATUS_DRAFT] ?? 'Nháp';
    }

    /**
     * @return array<int, string>
     */
    public function post(?int $actorId = null, ?string $workflowReason = null): array
    {
        return app(MaterialIssueNoteWorkflowService::class)->post($this, $workflowReason, $actorId);
    }

    public function cancel(?string $workflowReason = null, ?int $actorId = null): self
    {
        return app(MaterialIssueNoteWorkflowService::class)->cancel($this, $workflowReason, $actorId);
    }

    public static function generateNoteNo(): string
    {
        $date = now()->format('Ymd');
        $lastNo = static::query()
            ->whereDate('created_at', today())
            ->where('note_no', 'like', "MI-{$date}-%")
            ->orderByDesc('id')
            ->value('note_no');

        $lastSequence = 0;
        if (is_string($lastNo) && preg_match('/MI-\d{8}-(\d{4})$/', $lastNo, $matches) === 1) {
            $lastSequence = (int) ($matches[1] ?? 0);
        }

        return sprintf('MI-%s-%04d', $date, $lastSequence + 1);
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
}
