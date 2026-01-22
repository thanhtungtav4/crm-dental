<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'tax_code',
        'contact_person',
        'phone',
        'email',
        'address',
        'website',
        'payment_terms',
        'notes',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MaterialBatch::class);
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
        return $query->where('active', true);
    }

    /**
     * Helper Methods
     */
    public function getTotalMaterialsCount(): int
    {
        return $this->materials()->count();
    }

    public function getTotalBatchesCount(): int
    {
        return $this->batches()->count();
    }
}
