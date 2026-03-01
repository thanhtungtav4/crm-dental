<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TreatmentMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_session_id',
        'material_id',
        'batch_id',
        'quantity',
        'cost',
        'used_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $usage): void {
            $quantity = (int) $usage->quantity;

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng vật tư phải lớn hơn 0.',
                ]);
            }

            $material = Material::query()->find($usage->material_id);

            if (! $material) {
                return;
            }

            $session = TreatmentSession::query()
                ->with('treatmentPlan:id,branch_id')
                ->find($usage->treatment_session_id);

            $sessionBranchId = $session?->treatmentPlan?->branch_id;
            $materialBranchId = $material->branch_id;

            if ($sessionBranchId !== null && $materialBranchId !== null && (int) $sessionBranchId !== (int) $materialBranchId) {
                throw ValidationException::withMessages([
                    'material_id' => 'Vật tư không cùng chi nhánh với phiên điều trị đã chọn.',
                ]);
            }

            $authUser = auth()->user();
            $resolvedBranchId = $sessionBranchId !== null ? (int) $sessionBranchId : ($materialBranchId !== null ? (int) $materialBranchId : null);

            if ($authUser instanceof User && ! $authUser->canAccessBranch($resolvedBranchId)) {
                throw ValidationException::withMessages([
                    'treatment_session_id' => 'Bạn không có quyền ghi nhận vật tư cho chi nhánh này.',
                ]);
            }

            if (blank($usage->used_by) && $authUser instanceof User) {
                $usage->used_by = $authUser->id;
            }

            if ((int) $material->stock_qty < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng sử dụng vượt quá tồn kho hiện tại.',
                ]);
            }

            if ($usage->cost === null) {
                $usage->cost = round(((float) ($material->cost_price ?? $material->sale_price ?? 0)) * $quantity, 2);
            }
        });

        static::created(function (self $usage) {
            if (! $usage->material_id || ! $usage->quantity) {
                return;
            }

            $material = Material::query()->find($usage->material_id);
            if ($material) {
                $material->decrement('stock_qty', (int) $usage->quantity);

                InventoryTransaction::query()->create([
                    'material_id' => $material->id,
                    'branch_id' => $material->branch_id,
                    'treatment_session_id' => $usage->treatment_session_id,
                    'type' => 'out',
                    'quantity' => (int) $usage->quantity,
                    'unit_cost' => (float) ($material->cost_price ?? $material->sale_price ?? 0),
                    'note' => 'Auto: used in treatment',
                    'created_by' => $usage->used_by,
                ]);
            }
        });

        static::deleted(function (self $usage) {
            if (! $usage->material_id || ! $usage->quantity) {
                return;
            }

            $material = Material::query()->find($usage->material_id);
            if ($material) {
                $material->increment('stock_qty', (int) $usage->quantity);

                InventoryTransaction::query()->create([
                    'material_id' => $material->id,
                    'branch_id' => $material->branch_id,
                    'treatment_session_id' => $usage->treatment_session_id,
                    'type' => 'adjust',
                    'quantity' => (int) $usage->quantity,
                    'unit_cost' => (float) ($material->cost_price ?? $material->sale_price ?? 0),
                    'note' => 'Auto: revert usage delete',
                    'created_by' => $usage->used_by,
                ]);
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->session?->treatmentPlan?->branch_id ?? $this->material?->branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }
}
