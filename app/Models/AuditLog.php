<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public const ENTITY_PAYMENT = 'payment';

    public const ENTITY_INVOICE = 'invoice';

    public const ENTITY_APPOINTMENT = 'appointment';

    public const ENTITY_CARE_TICKET = 'care_ticket';

    public const ENTITY_PLAN_ITEM = 'plan_item';

    public const ENTITY_MASTER_DATA_SYNC = 'master_data_sync';

    public const ENTITY_MASTER_PATIENT_INDEX = 'master_patient_index';

    public const ENTITY_REPORT_SNAPSHOT = 'report_snapshot';

    public const ENTITY_AUTOMATION = 'automation';

    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_REFUND = 'refund';

    public const ACTION_REVERSAL = 'reversal';

    public const ACTION_CANCEL = 'cancel';

    public const ACTION_RESCHEDULE = 'reschedule';

    public const ACTION_NO_SHOW = 'no_show';

    public const ACTION_COMPLETE = 'complete';

    public const ACTION_FOLLOW_UP = 'follow_up';

    public const ACTION_FAIL = 'fail';

    public const ACTION_SYNC = 'sync';

    public const ACTION_SNAPSHOT = 'snapshot';

    public const ACTION_SLA_CHECK = 'sla_check';

    public const ACTION_DEDUPE = 'dedupe';

    public const ACTION_RUN = 'run';

    public const ACTION_APPROVE = 'approve';

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $entityType,
        int $entityId,
        string $action,
        ?int $actorId = null,
        array $metadata = []
    ): self {
        return self::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);
    }
}
