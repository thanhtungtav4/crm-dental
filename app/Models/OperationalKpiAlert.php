<?php

namespace App\Models;

use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalKpiAlert extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_ACK = 'ack';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'snapshot_id',
        'snapshot_key',
        'snapshot_date',
        'branch_id',
        'owner_user_id',
        'metric_key',
        'threshold_direction',
        'threshold_value',
        'observed_value',
        'severity',
        'status',
        'title',
        'message',
        'metadata',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'threshold_value' => 'decimal:2',
            'observed_value' => 'decimal:2',
            'metadata' => 'array',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ReportSnapshot::class, 'snapshot_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function markAcknowledged(?int $actorId = null): void
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền xác nhận cảnh báo KPI.',
        );

        $this->forceFill([
            'status' => self::STATUS_ACK,
            'acknowledged_by' => $actorId ?? auth()->id(),
            'acknowledged_at' => now(),
        ])->save();
    }

    public function markResolved(?int $actorId = null, ?string $note = null): void
    {
        ActionGate::authorize(
            ActionPermission::AUTOMATION_RUN,
            'Bạn không có quyền xử lý cảnh báo KPI.',
        );

        $this->forceFill([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $actorId ?? auth()->id(),
            'resolved_at' => now(),
            'resolution_note' => $note,
        ])->save();
    }
}
