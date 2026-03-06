<?php

namespace App\Models;

use App\Services\InventoryMutationService;
use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialIssueNote extends Model
{
    use SoftDeletes;

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
            if (blank($note->branch_id) && $note->patient_id) {
                $note->branch_id = Patient::query()
                    ->whereKey((int) $note->patient_id)
                    ->value('first_branch_id');
            }

            if (is_numeric($note->branch_id)) {
                BranchAccess::assertCanAccessBranch(
                    branchId: (int) $note->branch_id,
                    field: 'branch_id',
                    message: 'Bạn không có quyền thao tác phiếu xuất ở chi nhánh này.',
                );
            }

            if ($note->exists && $note->isDirty('status')) {
                $fromStatus = (string) ($note->getOriginal('status') ?? static::STATUS_DRAFT);
                $toStatus = (string) $note->status;

                if (! static::canTransitionStatus($fromStatus, $toStatus)) {
                    throw ValidationException::withMessages([
                        'status' => sprintf('Không thể chuyển trạng thái phiếu xuất từ "%s" sang "%s".', $fromStatus, $toStatus),
                    ]);
                }
            }

            if (
                $note->exists
                && (string) ($note->getOriginal('status') ?? static::STATUS_DRAFT) === static::STATUS_POSTED
                && $note->isDirty()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu đã xuất kho không thể chỉnh sửa.',
                ]);
            }
        });

        static::deleting(function (self $note): void {
            if ($note->status === static::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'status' => 'Phiếu đã xuất kho không thể xóa.',
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

    /**
     * @return array<int, string>
     */
    public function post(?int $actorId = null): array
    {
        $lowStockWarnings = [];

        DB::transaction(function () use (&$lowStockWarnings, $actorId): void {
            $lockedNote = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedNote->status === static::STATUS_POSTED) {
                return;
            }

            if ($lockedNote->status !== static::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ phiếu nháp mới được xuất kho.',
                ]);
            }

            $items = $lockedNote->items()
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Phiếu xuất chưa có vật tư.',
                ]);
            }

            foreach ($items as $item) {
                $noteBranchId = is_numeric($lockedNote->branch_id) ? (int) $lockedNote->branch_id : null;
                $quantity = (int) $item->quantity;

                $mutation = app(InventoryMutationService::class)->consumeBatch(
                    materialId: (int) $item->material_id,
                    batchId: (int) $item->material_batch_id,
                    quantity: $quantity,
                    expectedBranchId: $noteBranchId,
                    branchMismatchMessage: 'Vat tu khong thuoc chi nhanh cua phieu xuat.',
                );

                $material = $mutation['material'];
                $materialBatch = $mutation['batch'];

                if ($material->isLowStock()) {
                    $lowStockWarnings[] = sprintf(
                        '%s (tồn: %d, min: %d)',
                        (string) $material->name,
                        (int) ($material->stock_qty ?? 0),
                        (int) ($material->min_stock ?? 0),
                    );
                }

                InventoryTransaction::query()->create([
                    'material_id' => $material->id,
                    'material_batch_id' => $materialBatch->id,
                    'branch_id' => $lockedNote->branch_id,
                    'material_issue_note_id' => $lockedNote->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'note' => 'Xuất theo phiếu: '.$lockedNote->note_no,
                    'created_by' => $actorId ?? auth()->id(),
                ]);
            }

            $lockedNote->forceFill([
                'status' => static::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $actorId ?? auth()->id(),
            ])->save();
        }, 3);

        $this->refresh();

        return array_values(array_unique($lowStockWarnings));
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
}
