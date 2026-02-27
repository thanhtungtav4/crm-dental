<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'description',
        'unit',
        'duration_minutes',
        'tooth_specific',
        'requires_consent',
        'workflow_type',
        'protocol_id',
        'default_materials',
        'doctor_commission_rate',
        'branch_id',
        'default_price',
        'vat_rate',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'tooth_specific' => 'boolean',
        'requires_consent' => 'boolean',
        'default_materials' => 'array',
        'duration_minutes' => 'integer',
        'doctor_commission_rate' => 'decimal:2',
        'default_price' => 'decimal:2',
        'vat_rate' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Service category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * Branch (if service is branch-specific)
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Scope: Only active services
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope: Services by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: For specific branch or all branches
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
        });
    }

    public function scopeRequiringConsent($query)
    {
        return $query->where('requires_consent', true);
    }
}
