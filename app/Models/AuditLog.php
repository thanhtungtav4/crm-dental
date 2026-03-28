<?php

namespace App\Models;

use App\Support\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'actor_id',
        'branch_id',
        'patient_id',
        'metadata',
        'occurred_at',
    ];

    public const ENTITY_PAYMENT = 'payment';

    public const ENTITY_INVOICE = 'invoice';

    public const ENTITY_PATIENT_WALLET = 'patient_wallet';

    public const ENTITY_PRESCRIPTION = 'prescription';

    public const ENTITY_APPOINTMENT = 'appointment';

    public const ENTITY_POPUP_ANNOUNCEMENT = 'popup_announcement';

    public const ENTITY_CARE_TICKET = 'care_ticket';

    public const ENTITY_PLAN_ITEM = 'plan_item';

    public const ENTITY_CONSENT = 'consent';

    public const ENTITY_INSURANCE_CLAIM = 'insurance_claim';

    public const ENTITY_TREATMENT_PLAN = 'treatment_plan';

    public const ENTITY_TREATMENT_SESSION = 'treatment_session';

    public const ENTITY_FACTORY_ORDER = 'factory_order';

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

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'actor_id' => 'integer',
            'branch_id' => 'integer',
            'patient_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $auditLog): void {
            $metadata = $auditLog->normalizeMetadata();
            $patientId = $auditLog->patient_id ?? static::normalizeNullableInt(data_get($metadata, 'patient_id'));
            $branchId = $auditLog->branch_id
                ?? static::resolveBranchIdFromMetadata($metadata)
                ?? static::resolveBranchIdFromPatient($patientId);

            $auditLog->patient_id = $patientId;
            $auditLog->branch_id = $branchId;
            $auditLog->metadata = $metadata;
            $auditLog->occurred_at = $auditLog->occurred_at ?? now();
        });

        static::updating(function (): void {
            throw ValidationException::withMessages([
                'audit_log' => 'Audit log là immutable, không cho phép cập nhật.',
            ]);
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'audit_log' => 'Audit log là immutable, không cho phép xóa.',
            ]);
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
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
            $query->whereIn('branch_id', $branchIds)
                ->orWhereIn('patient_id', Patient::query()
                    ->whereIn('first_branch_id', $branchIds)
                    ->select('id'));

            foreach (static::branchScopeMetadataKeys() as $metadataKey) {
                foreach ($branchIds as $branchId) {
                    $query->orWhere("metadata->{$metadataKey}", $branchId);
                }
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

        if (is_numeric($this->patient_id) && in_array((int) $this->patient?->first_branch_id, $branchIds, true)) {
            return true;
        }

        $patientId = $this->patient_id ?? data_get($this->metadata, 'patient_id');

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
        array $metadata = [],
        ?int $branchId = null,
        ?int $patientId = null,
        \DateTimeInterface|string|int|null $occurredAt = null,
    ): self {
        return self::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor_id' => $actorId,
            'branch_id' => $branchId,
            'patient_id' => $patientId,
            'metadata' => $metadata,
            'occurred_at' => $occurredAt,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeMetadata(): array
    {
        if (is_array($this->metadata)) {
            return $this->metadata;
        }

        $metadata = json_decode((string) $this->metadata, true);

        return is_array($metadata) ? $metadata : [];
    }

    protected static function resolveBranchIdFromMetadata(array $metadata): ?int
    {
        foreach (static::branchScopeMetadataKeys() as $metadataKey) {
            $branchId = static::normalizeNullableInt(data_get($metadata, $metadataKey));

            if ($branchId !== null) {
                return $branchId;
            }
        }

        return null;
    }

    protected static function resolveBranchIdFromPatient(?int $patientId): ?int
    {
        if ($patientId === null) {
            return null;
        }

        $branchId = Patient::query()
            ->whereKey($patientId)
            ->value('first_branch_id');

        return static::normalizeNullableInt($branchId);
    }

    protected static function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
