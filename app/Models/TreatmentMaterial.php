<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TreatmentMaterial extends Model
{
    use HasFactory;

    protected static int $managedPersistenceDepth = 0;

    protected $fillable = [
        'treatment_session_id',
        'material_id',
        'batch_id',
        'quantity',
        'cost',
        'used_by',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'quantity' => 'integer',
        'cost' => 'decimal:2',
        'used_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $usage): void {
            if (! static::isManagedPersistence()) {
                throw ValidationException::withMessages([
                    'treatment_material' => 'TreatmentMaterial changes must go through TreatmentMaterialUsageService.',
                ]);
            }

            if ((int) $usage->quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'So luong vat tu phai lon hon 0.',
                ]);
            }

            if (! is_numeric($usage->batch_id)) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Vui long chon lo vat tu de dam bao truy vet ton kho.',
                ]);
            }

            if (blank($usage->used_by) && is_numeric(auth()->id())) {
                $usage->used_by = (int) auth()->id();
            }
        });

        static::updating(function (): void {
            throw ValidationException::withMessages([
                'treatment_material' => 'Khong ho tro chinh sua vat tu da ghi nhan de tranh sai lech ton kho. Vui long xoa va tao lai.',
            ]);
        });

        static::deleting(function (): void {
            if (static::isManagedPersistence()) {
                return;
            }

            throw ValidationException::withMessages([
                'treatment_material' => 'TreatmentMaterial changes must go through TreatmentMaterialUsageService.',
            ]);
        });
    }

    public static function runWithinManagedPersistence(callable $callback): mixed
    {
        static::$managedPersistenceDepth++;

        try {
            return $callback();
        } finally {
            static::$managedPersistenceDepth = max(0, static::$managedPersistenceDepth - 1);
        }
    }

    protected static function isManagedPersistence(): bool
    {
        return static::$managedPersistenceDepth > 0;
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
