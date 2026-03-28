<?php

namespace App\Models;

use App\Services\MasterPatientDuplicateWorkflowService;
use App\Support\ActionPermission;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class MasterPatientDuplicate extends Model
{
    use HasFactory;

    protected static bool $allowsManagedWorkflowMutation = false;

    /**
     * @var array<string, mixed>
     */
    protected static array $managedTransitionContext = [];

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    public const STALE_OPEN_CASE_DAYS = 3;

    protected const STATUS_TRANSITIONS = [
        self::STATUS_OPEN => [
            self::STATUS_OPEN,
            self::STATUS_RESOLVED,
            self::STATUS_IGNORED,
        ],
        self::STATUS_RESOLVED => [
            self::STATUS_RESOLVED,
            self::STATUS_OPEN,
            self::STATUS_IGNORED,
        ],
        self::STATUS_IGNORED => [
            self::STATUS_IGNORED,
            self::STATUS_OPEN,
            self::STATUS_RESOLVED,
        ],
    ];

    protected $fillable = [
        'patient_id',
        'branch_id',
        'identity_type',
        'identity_hash',
        'identity_value',
        'matched_patient_ids',
        'matched_branch_ids',
        'confidence_score',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'matched_patient_ids' => 'array',
            'matched_branch_ids' => 'array',
            'confidence_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $duplicate): void {
            $duplicate->status = static::normalizeStatus($duplicate->status) ?? static::STATUS_OPEN;

            if (! $duplicate->exists || ! $duplicate->isDirty('status')) {
                return;
            }

            if (! static::$allowsManagedWorkflowMutation) {
                throw ValidationException::withMessages([
                    'status' => 'Trang thai duplicate case chi duoc thay doi qua MasterPatientDuplicateWorkflowService.',
                ]);
            }

            $fromStatus = static::normalizeStatus((string) ($duplicate->getOriginal('status') ?: static::STATUS_OPEN))
                ?? static::STATUS_OPEN;

            if (! static::canTransition($fromStatus, $duplicate->status)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'MPI_DUPLICATE_STATE_INVALID: Không thể chuyển từ "%s" sang "%s".',
                        static::statusLabel($fromStatus),
                        static::statusLabel($duplicate->status),
                    ),
                ]);
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function merges(): HasMany
    {
        return $this->hasMany(MasterPatientMerge::class, 'duplicate_case_id');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->can(ActionPermission::MPI_DEDUPE_REVIEW)) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Admin')) {
            return $query;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user, false);

        if ($branchIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $duplicateQuery) use ($branchIds): void {
            $duplicateQuery->whereIn('branch_id', $branchIds)
                ->orWhereHas('patient', function (Builder $patientQuery) use ($branchIds): void {
                    $patientQuery->whereIn('first_branch_id', $branchIds);
                });

            foreach ($branchIds as $branchId) {
                $duplicateQuery->orWhereJsonContains('matched_branch_ids', $branchId);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if (! $user->can(ActionPermission::MPI_DEDUPE_REVIEW)) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user, false);

        if ($branchIds === []) {
            return false;
        }

        if (is_numeric($this->branch_id) && in_array((int) $this->branch_id, $branchIds, true)) {
            return true;
        }

        $patientBranchId = $this->patient?->first_branch_id;

        if (is_numeric($patientBranchId) && in_array((int) $patientBranchId, $branchIds, true)) {
            return true;
        }

        return count(array_intersect($this->matchedBranchIds(), $branchIds)) > 0;
    }

    public function isReviewableBy(User $user): bool
    {
        if (! $user->can(ActionPermission::MPI_DEDUPE_REVIEW)) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $branchIds = BranchAccess::accessibleBranchIds($user, false);
        $requiredBranchIds = $this->reviewScopeBranchIds();

        if ($branchIds === [] || $requiredBranchIds === []) {
            return false;
        }

        return array_diff($requiredBranchIds, $branchIds) === [];
    }

    public function canBeMerged(): bool
    {
        return $this->status === self::STATUS_OPEN && count($this->matchedPatientIds()) >= 2;
    }

    public function canBeIgnored(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isStaleOpenCase(?CarbonInterface $referenceTime = null): bool
    {
        if ($this->status !== self::STATUS_OPEN || ! $this->created_at instanceof CarbonInterface) {
            return false;
        }

        return $this->created_at->lte(static::staleOpenCaseThreshold($referenceTime));
    }

    public static function staleOpenCaseThreshold(?CarbonInterface $referenceTime = null): CarbonInterface
    {
        return ($referenceTime ?? now())->copy()->subDays(self::STALE_OPEN_CASE_DAYS);
    }

    public function latestAppliedMerge(): ?MasterPatientMerge
    {
        return $this->merges()
            ->where('status', MasterPatientMerge::STATUS_APPLIED)
            ->latest('id')
            ->first();
    }

    public function matchedPatientIds(): array
    {
        return collect($this->matched_patient_ids ?? [])
            ->push($this->patient_id)
            ->filter(static fn (mixed $patientId): bool => is_numeric($patientId) && (int) $patientId > 0)
            ->map(static fn (mixed $patientId): int => (int) $patientId)
            ->unique()
            ->values()
            ->all();
    }

    public function matchedBranchIds(): array
    {
        return collect($this->matched_branch_ids ?? [])
            ->push($this->branch_id)
            ->push($this->patient?->first_branch_id)
            ->filter(static fn (mixed $branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(static fn (mixed $branchId): int => (int) $branchId)
            ->unique()
            ->values()
            ->all();
    }

    public function defaultCanonicalPatientId(): ?int
    {
        if (is_numeric($this->patient_id) && in_array((int) $this->patient_id, $this->matchedPatientIds(), true)) {
            return (int) $this->patient_id;
        }

        return $this->matchedPatientIds()[0] ?? null;
    }

    public function defaultMergedPatientId(): ?int
    {
        $canonicalPatientId = $this->defaultCanonicalPatientId();

        return collect($this->matchedPatientIds())
            ->first(fn (int $patientId): bool => $patientId !== $canonicalPatientId);
    }

    /**
     * @return array<int, array{patient_id:int,patient_code:?string,full_name:string,branch_name:string,status:string,phone:?string,email:?string}>
     */
    public function matchedPatientsForReview(?User $user = null): array
    {
        $patientIds = $this->matchedPatientIds();

        if ($patientIds === []) {
            return [];
        }

        $query = Patient::query()
            ->with('branch')
            ->whereIn('id', $patientIds);

        if ($user instanceof User && ! $user->hasRole('Admin')) {
            $branchIds = BranchAccess::accessibleBranchIds($user, false);

            if ($branchIds === []) {
                return [];
            }

            $query->whereIn('first_branch_id', $branchIds);
        }

        $patients = $query->get()
            ->sortBy(static fn (Patient $patient): int => array_search($patient->id, $patientIds, true) ?: 0)
            ->values();

        return $patients
            ->map(static function (Patient $patient): array {
                return [
                    'patient_id' => $patient->id,
                    'patient_code' => $patient->patient_code,
                    'full_name' => $patient->full_name,
                    'branch_name' => $patient->branch?->name ?? '-',
                    'status' => (string) ($patient->status ?? '-'),
                    'phone' => $patient->phone,
                    'email' => $patient->email,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function matchedPatientOptionsForReview(?User $user = null): array
    {
        return collect($this->matchedPatientsForReview($user))
            ->mapWithKeys(static function (array $patient): array {
                $labelParts = array_filter([
                    $patient['patient_code'] ?: '#'.$patient['patient_id'],
                    $patient['full_name'],
                    $patient['branch_name'],
                ]);

                return [
                    $patient['patient_id'] => implode(' | ', $labelParts),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{merge_id:int,status:string,canonical_patient:string,merged_patient:string,merged_by:string,merged_at:?string,rolled_back_at:?string,rollback_note:?string}>
     */
    public function mergeHistoryForReview(?User $user = null): array
    {
        return $this->merges()
            ->with([
                'canonicalPatient.branch',
                'mergedPatient.branch',
                'mergedByUser',
                'rolledBackByUser',
            ])
            ->latest('id')
            ->get()
            ->map(function (MasterPatientMerge $merge) use ($user): array {
                $canSeeCanonical = $this->canSeePatientForReview($merge->canonicalPatient, $user);
                $canSeeMerged = $this->canSeePatientForReview($merge->mergedPatient, $user);

                return [
                    'merge_id' => $merge->id,
                    'status' => $merge->status,
                    'canonical_patient' => $canSeeCanonical
                        ? $this->formatPatientReference($merge->canonicalPatient)
                        : 'Ngoài phạm vi chi nhánh',
                    'merged_patient' => $canSeeMerged
                        ? $this->formatPatientReference($merge->mergedPatient)
                        : 'Ngoài phạm vi chi nhánh',
                    'merged_by' => $merge->mergedByUser?->name ?? 'Hệ thống',
                    'merged_at' => $merge->merged_at?->format('d/m/Y H:i'),
                    'rolled_back_at' => $merge->rolled_back_at?->format('d/m/Y H:i'),
                    'rollback_note' => $merge->rollback_note,
                ];
            })
            ->all();
    }

    public function markResolved(?int $reviewedBy = null, ?string $note = null): void
    {
        app(MasterPatientDuplicateWorkflowService::class)->resolve(
            duplicateCase: $this,
            note: $note,
            actorId: $reviewedBy,
        );
    }

    public function markIgnored(?int $reviewedBy = null, ?string $note = null): void
    {
        app(MasterPatientDuplicateWorkflowService::class)->ignore(
            duplicateCase: $this,
            note: $note,
            actorId: $reviewedBy,
        );
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Đang chờ review',
            self::STATUS_RESOLVED => 'Đã resolve',
            self::STATUS_IGNORED => 'Đã bỏ qua',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        $normalizedStatus = static::normalizeStatus($status);

        return static::statusOptions()[$normalizedStatus ?? ''] ?? ($status ?: '-');
    }

    public static function statusColor(?string $status): string
    {
        return match (static::normalizeStatus($status)) {
            self::STATUS_OPEN => 'warning',
            self::STATUS_RESOLVED => 'success',
            self::STATUS_IGNORED => 'gray',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function identityTypeOptions(): array
    {
        return [
            MasterPatientIdentity::TYPE_PHONE => 'Điện thoại',
            MasterPatientIdentity::TYPE_EMAIL => 'Email',
            MasterPatientIdentity::TYPE_CCCD => 'CCCD',
        ];
    }

    public static function identityTypeLabel(?string $identityType): string
    {
        return static::identityTypeOptions()[$identityType ?? ''] ?? ($identityType ?: '-');
    }

    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalizedStatus = strtolower(trim($status));

        return in_array($normalizedStatus, array_keys(static::statusOptions()), true)
            ? $normalizedStatus
            : null;
    }

    public static function canTransition(?string $fromStatus, ?string $toStatus): bool
    {
        $normalizedFromStatus = static::normalizeStatus($fromStatus);
        $normalizedToStatus = static::normalizeStatus($toStatus);

        if ($normalizedFromStatus === null || $normalizedToStatus === null) {
            return false;
        }

        return in_array($normalizedToStatus, static::STATUS_TRANSITIONS[$normalizedFromStatus] ?? [], true);
    }

    public static function runWithinManagedWorkflow(callable $callback, array $context = []): mixed
    {
        $previousState = static::$allowsManagedWorkflowMutation;
        $previousContext = static::$managedTransitionContext;
        static::$allowsManagedWorkflowMutation = true;
        static::$managedTransitionContext = $context;

        try {
            return $callback();
        } finally {
            static::$allowsManagedWorkflowMutation = $previousState;
            static::$managedTransitionContext = $previousContext;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function currentManagedTransitionContext(): array
    {
        return static::$managedTransitionContext;
    }

    /**
     * @return array<int, int>
     */
    protected function reviewScopeBranchIds(): array
    {
        $branchIds = $this->matchedBranchIds();

        if ($branchIds !== []) {
            return $branchIds;
        }

        $patientBranchId = is_numeric($this->patient_id)
            ? Patient::query()->whereKey((int) $this->patient_id)->value('first_branch_id')
            : null;

        return is_numeric($patientBranchId)
            ? [(int) $patientBranchId]
            : [];
    }

    protected function canSeePatientForReview(?Patient $patient, ?User $user): bool
    {
        if (! $patient instanceof Patient) {
            return false;
        }

        if (! $user instanceof User || $user->hasRole('Admin')) {
            return true;
        }

        return $user->canAccessBranch(is_numeric($patient->first_branch_id) ? (int) $patient->first_branch_id : null);
    }

    protected function formatPatientReference(?Patient $patient): string
    {
        if (! $patient instanceof Patient) {
            return '-';
        }

        return collect([
            $patient->patient_code,
            $patient->full_name,
            $patient->branch?->name,
        ])
            ->filter(static fn (mixed $value): bool => filled($value))
            ->implode(' | ');
    }
}
