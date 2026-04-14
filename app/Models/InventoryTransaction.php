<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'material_batch_id',
        'branch_id',
        'treatment_session_id',
        'material_issue_note_id',
        'type',
        'quantity',
        'unit_cost',
        'note',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw ValidationException::withMessages([
                'inventory_transaction' => 'Inventory transaction là immutable, không cho phép cập nhật.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'inventory_transaction' => 'Inventory transaction là immutable, không cho phép xóa.',
            ]);
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class, 'material_batch_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    public function issueNote(): BelongsTo
    {
        return $this->belongsTo(MaterialIssueNote::class, 'material_issue_note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
