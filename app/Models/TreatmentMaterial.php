<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    protected static function booted(): void
    {
        static::created(function (self $usage) {
            if (!$usage->material_id || !$usage->quantity) return;

            $material = Material::find($usage->material_id);
            if ($material) {
                // Decrease stock
                $material->decrement('stock_qty', (int) $usage->quantity);

                // Log inventory transaction
                InventoryTransaction::create([
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
            if (!$usage->material_id || !$usage->quantity) return;

            $material = Material::find($usage->material_id);
            if ($material) {
                // Restore stock on delete
                $material->increment('stock_qty', (int) $usage->quantity);

                InventoryTransaction::create([
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
}
