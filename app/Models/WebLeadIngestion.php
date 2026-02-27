<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebLeadIngestion extends Model
{
    /** @use HasFactory<\Database\Factories\WebLeadIngestionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATED = 'created';

    public const STATUS_MERGED = 'merged';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'request_id',
        'source',
        'full_name',
        'phone',
        'phone_normalized',
        'branch_code',
        'branch_id',
        'customer_id',
        'status',
        'payload',
        'response',
        'error_message',
        'ip_address',
        'user_agent',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
