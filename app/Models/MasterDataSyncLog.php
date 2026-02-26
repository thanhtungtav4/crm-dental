<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterDataSyncLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'entity',
        'source_branch_id',
        'target_branch_id',
        'dry_run',
        'synced_count',
        'skipped_count',
        'conflict_count',
        'status',
        'started_at',
        'finished_at',
        'metadata',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'synced_count' => 'integer',
            'skipped_count' => 'integer',
            'conflict_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function targetBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'target_branch_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
