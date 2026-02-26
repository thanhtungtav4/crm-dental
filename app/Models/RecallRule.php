<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecallRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'service_id',
        'name',
        'offset_days',
        'care_channel',
        'priority',
        'is_active',
        'rules',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'rules' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
