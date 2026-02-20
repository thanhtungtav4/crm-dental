<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiseaseGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get all diseases in this group
     */
    public function diseases(): HasMany
    {
        return $this->hasMany(Disease::class);
    }

    /**
     * Get active diseases count
     */
    public function getActiveDiseasesCountAttribute(): int
    {
        return $this->diseases()->where('is_active', true)->count();
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
