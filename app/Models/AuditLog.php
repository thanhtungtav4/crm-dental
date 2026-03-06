<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
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

    public const ENTITY_PRESCRIPTION = 'prescription';

    public const ENTITY_APPOINTMENT = 'appointment';

    public const ENTITY_CARE_TICKET = 'care_ticket';

    public const ENTITY_PLAN_ITEM = 'plan_item';

    public const ENTITY_CONSENT = 'consent';

    public const ENTITY_INSURANCE_CLAIM = 'insurance_claim';

    public const ENTITY_TREATMENT_SESSION = 'treatment_session';

    public const ENTITY_MASTER_DATA_SYNC = 'master_data_sync';

    public const ENTITY_MASTER_PATIENT_INDEX = 'master_patient_index';

    public const ENTITY_MASTER_PATIENT_DUPLICATE = 'master_patient_duplicate';

    public const ENTITY_MASTER_PATIENT_MERGE = 'master_patient_merge';

    public const ENTITY_BRANCH_TRANSFER = 'branch_transfer';

    public const ENTITY_REPORT_SNAPSHOT = 'report_snapshot';

    public const ENTITY_AUTOMATION = 'automation';

    public const ENTITY_SECURITY = 'security';

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

    public const ACTION_MERGE = 'merge';

    public const ACTION_ROLLBACK = 'rollback';

    public const ACTION_TRANSFER = 'transfer';

    public const ACTION_RUN = 'run';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_RESOLVE = 'resolve';

    public const ACTION_PRINT = 'print';

    public const ACTION_EXPORT = 'export';

    public const ACTION_READ = 'read';

    public const ACTION_BLOCK = 'block';

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        if (! $user->can('ViewAny:AuditLog')) {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = BranchAccess::accessibleBranchIds($user);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($branchIds): void {
            $hasCondition = false;

            foreach (static::branchScopeMetadataKeys() as $metadataKey) {
                foreach ($branchIds as $branchId) {
                    if ($hasCondition) {
                        $query->orWhere("metadata->{$metadataKey}", $branchId);
                    } else {
                        $query->where("metadata->{$metadataKey}", $branchId);
                        $hasCondition = true;
                    }
                }
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->can('View:AuditLog')) {
            return false;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user);

        if ($branchIds === []) {
            return false;
        }

        foreach (static::branchScopeMetadataKeys() as $metadataKey) {
            $branchId = data_get($this->metadata, $metadataKey);

            if (is_numeric($branchId) && in_array((int) $branchId, $branchIds, true)) {
                return true;
            }
        }

        $patientId = data_get($this->metadata, 'patient_id');

        if (! is_numeric($patientId)) {
            return false;
        }

        $patientBranchId = Patient::query()
            ->whereKey((int) $patientId)
            ->value('first_branch_id');

        return is_numeric($patientBranchId) && in_array((int) $patientBranchId, $branchIds, true);
    }

    /**
     * @return array<int, string>
     */
    public static function branchScopeMetadataKeys(): array
    {
        return [
            'branch_id',
            'from_branch_id',
            'to_branch_id',
            'source_branch_id',
        ];
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
