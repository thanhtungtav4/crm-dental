<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialBatch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'material_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'purchase_price',
        'received_date',
        'supplier_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_date' => 'date',
        'quantity' => 'integer',
        'purchase_price' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function treatmentMaterials(): HasMany
    {
        return $this->hasMany(TreatmentMaterial::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '>', now())
                     ->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeForMaterial($query, int $materialId)
    {
        return $query->where('material_id', $materialId);
    }

    /**
     * Helper Methods
     */
    public function isExpired(): bool
    {
        return $this->expiry_date < now();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date > now() && 
               $this->expiry_date <= now()->addDays($days);
    }

    public function getDaysUntilExpiry(): int
    {
        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function getExpiryStatusBadge(): string
    {
        if ($this->isExpired()) {
            return 'danger'; // Red
        } elseif ($this->isExpiringSoon(7)) {
            return 'danger'; // < 7 days = red
        } elseif ($this->isExpiringSoon(30)) {
            return 'warning'; // 7-30 days = yellow
        }
        return 'success'; // > 30 days = green
    }

    public function getExpiryWarningMessage(): ?string
    {
        $days = $this->getDaysUntilExpiry();
        
        if ($days < 0) {
            return '⚠️ ĐÃ HẾT HẠN ' . abs($days) . ' ngày!';
        } elseif ($days <= 7) {
            return '🚨 SẮP HẾT HẠN trong ' . $days . ' ngày!';
        } elseif ($days <= 30) {
            return '⚡ Sắp hết hạn trong ' . $days . ' ngày';
        }
        
        return null;
    }

    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    public function decreaseQuantity(int $amount): bool
    {
        if ($this->quantity < $amount) {
            return false;
        }

        $this->quantity -= $amount;
        
        if ($this->quantity == 0) {
            $this->status = 'depleted';
        }
        
        return $this->save();
    }

    public function increaseQuantity(int $amount): bool
    {
        $this->quantity += $amount;
        
        if ($this->status === 'depleted' && $this->quantity > 0) {
            $this->status = 'active';
        }
        
        return $this->save();
    }
}
