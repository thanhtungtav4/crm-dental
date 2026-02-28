<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TreatmentMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_session_id',
        'material_id',
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

            if ((int) $material->stock_qty < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Số lượng sử dụng vượt quá tồn kho hiện tại.',
                ]);
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
                    'unit_cost' => (float) ($material->unit_price ?? 0),
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
                    'unit_cost' => (float) ($material->unit_price ?? 0),
                    'note' => 'Auto: revert usage delete',
                    'created_by' => $usage->used_by,
                ]);
            }
        });
    }

    public function session()
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function resolveBranchId(): ?int
    {
        $branchId = $this->session?->treatmentPlan?->branch_id ?? $this->material?->branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }
}
