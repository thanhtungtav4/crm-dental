<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'sku',
        'unit',
        'stock_qty',
        'sale_price', // Renamed from unit_price
        'cost_price',
        'min_stock',
        'category',
        'manufacturer',
        'supplier_id',
        'reorder_point',
        'storage_location',
    ];

    protected $casts = [
        'stock_qty' => 'integer',
        'min_stock' => 'integer',
        'reorder_point' => 'integer',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MaterialBatch::class);
    }

    public function treatmentMaterials(): HasMany
    {
        return $this->hasMany(TreatmentMaterial::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Scopes
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_qty', '<=', 'min_stock');
    }

    public function scopeNeedReorder($query)
    {
        return $query->whereNotNull('reorder_point')
                     ->whereColumn('stock_qty', '<=', 'reorder_point');
    }

    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Helper Methods
     */
    public function hasActiveBatches(): bool
    {
        return $this->batches()->active()->inStock()->exists();
    }

    public function getActiveBatches()
    {
        return $this->batches()
                    ->active()
                    ->inStock()
                    ->orderBy('expiry_date', 'asc')
                    ->get();
    }

    public function hasExpiringBatches(int $days = 30): bool
    {
        return $this->batches()->expiringSoon($days)->exists();
    }

    public function getExpiringBatchesCount(int $days = 30): int
    {
        return $this->batches()->expiringSoon($days)->count();
    }

    public function getTotalBatchQuantity(): int
    {
        return $this->batches()->active()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->min_stock;
    }

    public function needsReorder(): bool
    {
        return $this->reorder_point && $this->stock_qty <= $this->reorder_point;
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'medicine' => 'Thuốc',
            'consumable' => 'Vật tư tiêu hao',
            'equipment' => 'Thiết bị',
            'dental_material' => 'Vật liệu nha',
            default => $this->category,
        };
    }
}
