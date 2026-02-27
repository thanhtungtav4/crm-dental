<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportSnapshot extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const SLA_ON_TIME = 'on_time';

    public const SLA_LATE = 'late';

    public const SLA_STALE = 'stale';

    public const SLA_MISSING = 'missing';

    protected $fillable = [
        'snapshot_key',
        'snapshot_date',
        'branch_id',
        'status',
        'sla_status',
        'generated_at',
        'sla_due_at',
        'payload',
        'lineage',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'generated_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'payload' => 'array',
            'lineage' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(OperationalKpiAlert::class, 'snapshot_id');
    }
}
