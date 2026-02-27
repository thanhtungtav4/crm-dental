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

    public const DRIFT_UNKNOWN = 'unknown';

    public const DRIFT_NONE = 'none';

    public const DRIFT_SCHEMA_CHANGED = 'schema_changed';

    public const DRIFT_FORMULA_CHANGED = 'formula_changed';

    public const DRIFT_SOURCE_CHANGED = 'source_changed';

    protected $fillable = [
        'snapshot_key',
        'schema_version',
        'snapshot_date',
        'branch_id',
        'branch_scope_id',
        'status',
        'sla_status',
        'generated_at',
        'sla_due_at',
        'payload',
        'payload_checksum',
        'lineage_checksum',
        'drift_status',
        'drift_details',
        'compared_snapshot_id',
        'lineage',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'branch_scope_id' => 'integer',
            'generated_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'payload' => 'array',
            'drift_details' => 'array',
            'lineage' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $snapshot): void {
            $snapshot->branch_scope_id = $snapshot->branch_id !== null
                ? (int) $snapshot->branch_id
                : 0;
        });
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

    public function comparedSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'compared_snapshot_id');
    }
}
