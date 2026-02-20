<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disease extends Model
{
    protected $fillable = [
        'disease_group_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the group this disease belongs to
     */
    public function diseaseGroup(): BelongsTo
    {
        return $this->belongsTo(DiseaseGroup::class);
    }

    /**
     * Scope to only active diseases
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by group
     */
    public function scopeInGroup($query, int $groupId)
    {
        return $query->where('disease_group_id', $groupId);
    }

    /**
     * Get full display name with code
     */
    public function getFullNameAttribute(): string
    {
        return "({$this->code}) {$this->name}";
    }
}
