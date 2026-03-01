<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCareQueueDailyAggregate extends Model
{
    protected $fillable = [
        'snapshot_date',
        'branch_id',
        'branch_scope_id',
        'care_type',
        'care_type_label',
        'care_status',
        'care_status_label',
        'total_count',
        'latest_care_at',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'branch_id' => 'integer',
            'branch_scope_id' => 'integer',
            'total_count' => 'integer',
            'latest_care_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
