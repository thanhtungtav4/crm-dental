<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRevenueDailyAggregate extends Model
{
    protected $fillable = [
        'snapshot_date',
        'branch_id',
        'branch_scope_id',
        'service_id',
        'service_name',
        'category_name',
        'total_count',
        'total_revenue',
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
            'service_id' => 'integer',
            'total_count' => 'integer',
            'total_revenue' => 'decimal:2',
            'generated_at' => 'datetime',
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
}
