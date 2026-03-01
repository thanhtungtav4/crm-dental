<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientIdentity;
use App\Models\MasterPatientMerge;
use App\Models\Patient;
use App\Support\ActionGate;
use App\Support\ActionPermission;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MasterPatientMergeService
{
    public function __construct(protected MasterPatientIndexService $mpiService) {}

    /**
     * @var array<int, string>
     */
    protected const DIRECT_REFERENCE_TABLES = [
        'appointments',
        'branch_logs',
        'exam_sessions',
        'clinical_notes',
        'consents',
        'installment_plans',
        'insurance_claims',
        'invoices',
        'notes',
        'patient_photos',
        'prescriptions',
        'treatment_plans',
        'visit_episodes',
    ];

    /**
     * @var array<int, string>
     */
    protected const SNAPSHOT_FIELDS = [
        'customer_id',
        'first_branch_id',
        'full_name',
        'birthday',
        'cccd',
        'gender',
        'phone',
        'phone_secondary',
        'email',
        'occupation',
        'address',
        'customer_group_id',
        'promotion_group_id',
        'primary_doctor_id',
        'owner_staff_id',
        'first_visit_reason',
        'note',
        'status',
        'medical_history',
        'verification_notes',
        'updated_by',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function merge(
        int $canonicalPatientId,
        int $mergedPatientId,
        ?int $duplicateCaseId = null,
        ?string $reason = null,
        ?int $actorId = null,
        array $metadata = [],
    ): MasterPatientMerge {
        ActionGate::authorize(
            ActionPermission::MPI_DEDUPE_REVIEW,
            'Bạn không có quyền xử lý queue trùng bệnh nhân liên chi nhánh.',
        );

        if ($canonicalPatientId === $mergedPatientId) {
            throw ValidationException::withMessages([
                'merged_patient_id' => 'Bệnh nhân chính và bệnh nhân gộp không được trùng nhau.',
            ]);
        }

        return DB::transaction(function () use (
            $canonicalPatientId,
            $mergedPatientId,
            $duplicateCaseId,
            $reason,
            $actorId,
            $metadata,
        ): MasterPatientMerge {
            $patients = Patient::query()
                ->whereIn('id', [$canonicalPatientId, $mergedPatientId])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $canonical = $patients->get($canonicalPatientId);
            $merged = $patients->get($mergedPatientId);

            if (! $canonical || ! $merged) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Không tìm thấy bệnh nhân để merge.',
                ]);
            }

            $existingAppliedMerge = MasterPatientMerge::query()
                ->where('merged_patient_id', $merged->id)
                ->where('status', MasterPatientMerge::STATUS_APPLIED)
                ->lockForUpdate()
                ->first();

            if ($existingAppliedMerge) {
                throw ValidationException::withMessages([
                    'merged_patient_id' => 'Bệnh nhân này đã được merge trước đó và chưa rollback.',
                ]);
            }

            $duplicateCase = $this->findDuplicateCase($duplicateCaseId);

            $canonicalBefore = $this->snapshotPatient($canonical);
            $mergedBefore = $this->snapshotPatient($merged);

            $canonicalUpdates = $this->buildGoldenRecordUpdates($canonicalBefore, $mergedBefore);
            if ($canonicalUpdates !== []) {
                $canonical->forceFill($canonicalUpdates)->save();
            }

            $mergedUpdates = $this->buildMergedPatientUpdates($canonical, $mergedBefore, $reason, $actorId);
            if ($mergedUpdates !== []) {
                $merged->forceFill($mergedUpdates)->save();
            }

            [$rewiredRecordIds, $rewireSummary, $conflicts] = $this->rewirePatientReferences(
                canonicalPatientId: $canonical->id,
                mergedPatientId: $merged->id,
                canonicalBranchId: $canonical->first_branch_id,
            );

            $resolvedCaseIds = $this->resolveRelatedDuplicateCases(
                canonicalPatientId: $canonical->id,
                canonicalBranchId: $canonical->first_branch_id,
                mergedPatientId: $merged->id,
                duplicateCase: $duplicateCase,
                reviewedBy: $actorId,
                reason: $reason,
            );

            $canonicalAfter = $this->snapshotPatient($canonical->fresh());
            $mergedAfter = $this->snapshotPatient($merged->fresh());

            $merge = MasterPatientMerge::query()->create([
                'canonical_patient_id' => $canonical->id,
                'merged_patient_id' => $merged->id,
                'duplicate_case_id' => $duplicateCase?->id,
                'status' => MasterPatientMerge::STATUS_APPLIED,
                'merge_reason' => $reason,
                'canonical_before' => $canonicalBefore,
                'canonical_after' => $canonicalAfter,
                'merged_before' => $mergedBefore,
                'merged_after' => $mergedAfter,
                'rewired_record_ids' => $rewiredRecordIds,
                'rewire_summary' => $rewireSummary,
                'metadata' => array_filter($metadata + [
                    'conflicts' => $conflicts,
                    'resolved_case_ids' => $resolvedCaseIds,
                ], fn ($value) => $value !== [] && $value !== null),
                'merged_by' => $actorId,
                'merged_at' => now(),
            ]);

            AuditLog::record(
                entityType: AuditLog::ENTITY_MASTER_PATIENT_MERGE,
                entityId: $merge->id,
                action: AuditLog::ACTION_MERGE,
                actorId: $actorId,
                metadata: [
                    'canonical_patient_id' => $canonical->id,
                    'merged_patient_id' => $merged->id,
                    'duplicate_case_id' => $duplicateCase?->id,
                    'rewire_summary' => $rewireSummary,
                    'conflicts' => $conflicts,
                ],
            );

            $this->mpiService->syncForPatient($canonical->fresh(), true);
            $this->mpiService->syncForPatient($merged->fresh(), true);

            return $merge;
        }, attempts: 3);
    }

    public function rollback(int $mergeId, ?string $note = null, ?int $actorId = null): MasterPatientMerge
    {
        ActionGate::authorize(
            ActionPermission::MPI_DEDUPE_REVIEW,
            'Bạn không có quyền xử lý queue trùng bệnh nhân liên chi nhánh.',
        );

        return DB::transaction(function () use ($mergeId, $note, $actorId): MasterPatientMerge {
            $merge = MasterPatientMerge::query()
                ->lockForUpdate()
                ->find($mergeId);

            if (! $merge) {
                throw ValidationException::withMessages([
                    'merge_id' => 'Không tìm thấy lịch sử merge cần rollback.',
                ]);
            }

            if ($merge->status !== MasterPatientMerge::STATUS_APPLIED) {
                throw ValidationException::withMessages([
                    'merge_id' => 'Merge này đã rollback hoặc không còn ở trạng thái applied.',
                ]);
            }

            $patients = Patient::query()
                ->whereIn('id', [$merge->canonical_patient_id, $merge->merged_patient_id])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $canonical = $patients->get($merge->canonical_patient_id);
            $merged = $patients->get($merge->merged_patient_id);

            if (! $canonical || ! $merged) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Không tìm thấy dữ liệu bệnh nhân để rollback merge.',
                ]);
            }

            $rewiredRecordIds = is_array($merge->rewired_record_ids)
                ? $merge->rewired_record_ids
                : [];

            foreach ($rewiredRecordIds as $table => $ids) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $normalizedIds = collect($ids)
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if ($normalizedIds === [] || ! Schema::hasColumn($table, 'patient_id')) {
                    continue;
                }

                DB::table($table)
                    ->whereIn('id', $normalizedIds)
                    ->where('patient_id', $canonical->id)
                    ->update(['patient_id' => $merged->id]);
            }

            $canonicalBefore = is_array($merge->canonical_before)
                ? Arr::only($merge->canonical_before, self::SNAPSHOT_FIELDS)
                : [];
            $mergedBefore = is_array($merge->merged_before)
                ? Arr::only($merge->merged_before, self::SNAPSHOT_FIELDS)
                : [];

            if ($canonicalBefore !== []) {
                $canonical->forceFill($canonicalBefore)->save();
            }

            if ($mergedBefore !== []) {
                $merged->forceFill($mergedBefore)->save();
            }

            $resolvedCaseIds = collect(data_get($merge->metadata, 'resolved_case_ids', []))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($resolvedCaseIds !== []) {
                $rollbackMatchedPatientIds = collect([$canonical->id, $merged->id])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $rollbackMatchedBranchIds = collect([$canonical->first_branch_id, $merged->first_branch_id])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                MasterPatientDuplicate::query()
                    ->whereIn('id', $resolvedCaseIds)
                    ->update([
                        'patient_id' => $merged->id,
                        'branch_id' => $merged->first_branch_id,
                        'matched_patient_ids' => $rollbackMatchedPatientIds,
                        'matched_branch_ids' => $rollbackMatchedBranchIds,
                        'status' => MasterPatientDuplicate::STATUS_OPEN,
                        'review_note' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
            }

            $merge->forceFill([
                'status' => MasterPatientMerge::STATUS_ROLLED_BACK,
                'rolled_back_by' => $actorId,
                'rolled_back_at' => now(),
                'rollback_note' => $note,
            ])->save();

            AuditLog::record(
                entityType: AuditLog::ENTITY_MASTER_PATIENT_MERGE,
                entityId: $merge->id,
                action: AuditLog::ACTION_ROLLBACK,
                actorId: $actorId,
                metadata: [
                    'canonical_patient_id' => $canonical->id,
                    'merged_patient_id' => $merged->id,
                    'rollback_note' => $note,
                ],
            );

            $this->mpiService->syncForPatient($merged->fresh(), true);
            $this->mpiService->syncForPatient($canonical->fresh(), true);

            return $merge->fresh();
        }, attempts: 3);
    }

    protected function findDuplicateCase(?int $duplicateCaseId): ?MasterPatientDuplicate
    {
        if ($duplicateCaseId === null) {
            return null;
        }

        $duplicateCase = MasterPatientDuplicate::query()
            ->lockForUpdate()
            ->find($duplicateCaseId);

        if (! $duplicateCase) {
            throw ValidationException::withMessages([
                'duplicate_case_id' => 'Không tìm thấy duplicate case để gắn với merge.',
            ]);
        }

        return $duplicateCase;
    }

    /**
     * @param  array<string, mixed>  $canonicalBefore
     * @param  array<string, mixed>  $mergedBefore
     * @return array<string, mixed>
     */
    protected function buildGoldenRecordUpdates(array $canonicalBefore, array $mergedBefore): array
    {
        $updates = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            if (in_array($field, ['status', 'verification_notes', 'updated_by'], true)) {
                continue;
            }

            $canonicalValue = $canonicalBefore[$field] ?? null;
            $mergedValue = $mergedBefore[$field] ?? null;

            if ($this->shouldAdoptValue($canonicalValue, $mergedValue)) {
                $updates[$field] = $mergedValue;
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $mergedBefore
     * @return array<string, mixed>
     */
    protected function buildMergedPatientUpdates(
        Patient $canonical,
        array $mergedBefore,
        ?string $reason,
        ?int $actorId,
    ): array {
        $existingNote = trim((string) ($mergedBefore['note'] ?? ''));
        $mergeMarker = sprintf(
            '[MERGED_TO:%s#%d]',
            (string) $canonical->patient_code,
            (int) $canonical->id,
        );

        $notes = collect([$existingNote, $mergeMarker])
            ->filter(fn (string $line) => trim($line) !== '')
            ->unique()
            ->implode("\n");

        $verificationLines = collect([
            trim((string) ($mergedBefore['verification_notes'] ?? '')),
            'Merged into patient #'.$canonical->id,
            $reason ? 'Reason: '.trim($reason) : null,
        ])
            ->filter(fn (?string $line) => filled($line))
            ->implode("\n");

        return [
            'status' => 'inactive',
            'note' => $notes,
            'verification_notes' => $verificationLines,
            'updated_by' => $actorId,
        ];
    }

    /**
     * @return array{0:array<string, array<int, int>>,1:array<string, int>,2:array<string, array<int, array<string, mixed>>>}
     */
    protected function rewirePatientReferences(
        int $canonicalPatientId,
        int $mergedPatientId,
        ?int $canonicalBranchId,
    ): array {
        $rewiredRecordIds = [];
        $rewireSummary = [];
        $conflicts = [];

        foreach (self::DIRECT_REFERENCE_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'patient_id')) {
                continue;
            }

            $ids = DB::table($table)
                ->where('patient_id', $mergedPatientId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($ids === []) {
                continue;
            }

            DB::table($table)
                ->whereIn('id', $ids)
                ->update(['patient_id' => $canonicalPatientId]);

            $rewiredRecordIds[$table] = $ids;
            $rewireSummary[$table] = count($ids);
        }

        if (Schema::hasTable('patient_medical_records')) {
            $mergedMedicalRecordId = DB::table('patient_medical_records')
                ->where('patient_id', $mergedPatientId)
                ->value('id');

            if ($mergedMedicalRecordId !== null) {
                $hasCanonicalMedicalRecord = DB::table('patient_medical_records')
                    ->where('patient_id', $canonicalPatientId)
                    ->exists();

                if ($hasCanonicalMedicalRecord) {
                    $conflicts['patient_medical_records'][] = [
                        'merged_record_id' => (int) $mergedMedicalRecordId,
                        'reason' => 'CANONICAL_ALREADY_HAS_RECORD',
                    ];
                } else {
                    DB::table('patient_medical_records')
                        ->where('id', (int) $mergedMedicalRecordId)
                        ->update(['patient_id' => $canonicalPatientId]);

                    $rewiredRecordIds['patient_medical_records'] = [(int) $mergedMedicalRecordId];
                    $rewireSummary['patient_medical_records'] = 1;
                }
            }
        }

        if (Schema::hasTable('patient_tooth_conditions')) {
            $movedToothConditionIds = [];

            $mergedToothConditions = DB::table('patient_tooth_conditions')
                ->where('patient_id', $mergedPatientId)
                ->get(['id', 'tooth_number', 'tooth_condition_id']);

            foreach ($mergedToothConditions as $toothCondition) {
                $existsOnCanonical = DB::table('patient_tooth_conditions')
                    ->where('patient_id', $canonicalPatientId)
                    ->where('tooth_number', $toothCondition->tooth_number)
                    ->where('tooth_condition_id', $toothCondition->tooth_condition_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($existsOnCanonical) {
                    $conflicts['patient_tooth_conditions'][] = [
                        'merged_record_id' => (int) $toothCondition->id,
                        'tooth_number' => $toothCondition->tooth_number,
                        'tooth_condition_id' => (int) $toothCondition->tooth_condition_id,
                        'reason' => 'DUPLICATE_TOOTH_CONDITION',
                    ];

                    continue;
                }

                DB::table('patient_tooth_conditions')
                    ->where('id', (int) $toothCondition->id)
                    ->update(['patient_id' => $canonicalPatientId]);

                $movedToothConditionIds[] = (int) $toothCondition->id;
            }

            if ($movedToothConditionIds !== []) {
                $rewiredRecordIds['patient_tooth_conditions'] = $movedToothConditionIds;
                $rewireSummary['patient_tooth_conditions'] = count($movedToothConditionIds);
            }
        }

        if (Schema::hasTable('master_patient_identities')) {
            [$identityMovedIds, $identityDeletedIds] = $this->mergeIdentities(
                canonicalPatientId: $canonicalPatientId,
                mergedPatientId: $mergedPatientId,
                canonicalBranchId: $canonicalBranchId,
            );

            if ($identityMovedIds !== []) {
                $rewiredRecordIds['master_patient_identities'] = $identityMovedIds;
                $rewireSummary['master_patient_identities'] = count($identityMovedIds);
            }

            if ($identityDeletedIds !== []) {
                $conflicts['master_patient_identities'][] = [
                    'deleted_duplicate_identity_ids' => $identityDeletedIds,
                    'reason' => 'IDENTITY_COLLAPSED_TO_CANONICAL',
                ];
            }
        }

        return [$rewiredRecordIds, $rewireSummary, $conflicts];
    }

    /**
     * @return array{0:array<int, int>,1:array<int, int>}
     */
    protected function mergeIdentities(
        int $canonicalPatientId,
        int $mergedPatientId,
        ?int $canonicalBranchId,
    ): array {
        $movedIds = [];
        $deletedIds = [];

        $identityRows = MasterPatientIdentity::query()
            ->where('patient_id', $mergedPatientId)
            ->lockForUpdate()
            ->get();

        foreach ($identityRows as $identity) {
            $existing = MasterPatientIdentity::query()
                ->where('patient_id', $canonicalPatientId)
                ->where('identity_type', $identity->identity_type)
                ->where('identity_hash', $identity->identity_hash)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'identity_value' => (string) ($existing->identity_value ?: $identity->identity_value),
                    'is_primary' => (bool) $existing->is_primary || (bool) $identity->is_primary,
                    'confidence_score' => max(
                        (float) $existing->confidence_score,
                        (float) $identity->confidence_score,
                    ),
                    'branch_id' => $existing->branch_id ?? $canonicalBranchId,
                ])->save();

                $deletedIds[] = $identity->id;
                $identity->delete();

                continue;
            }

            $identity->forceFill([
                'patient_id' => $canonicalPatientId,
                'branch_id' => $canonicalBranchId,
            ])->save();

            $movedIds[] = $identity->id;
        }

        return [$movedIds, $deletedIds];
    }

    /**
     * @return array<int, int>
     */
    protected function resolveRelatedDuplicateCases(
        int $canonicalPatientId,
        ?int $canonicalBranchId,
        int $mergedPatientId,
        ?MasterPatientDuplicate $duplicateCase,
        ?int $reviewedBy,
        ?string $reason,
    ): array {
        if (! Schema::hasTable('master_patient_duplicates')) {
            return [];
        }

        $cases = MasterPatientDuplicate::query()
            ->where('status', MasterPatientDuplicate::STATUS_OPEN)
            ->where(function ($query) use ($mergedPatientId, $duplicateCase): void {
                $query->where('patient_id', $mergedPatientId)
                    ->orWhereJsonContains('matched_patient_ids', $mergedPatientId);

                if ($duplicateCase) {
                    $query->orWhere('id', $duplicateCase->id);
                }
            })
            ->lockForUpdate()
            ->get();

        if ($duplicateCase && ! $cases->contains(fn (MasterPatientDuplicate $row) => $row->id === $duplicateCase->id)) {
            $cases->push($duplicateCase);
        }

        $resolvedCaseIds = [];

        foreach ($cases as $case) {
            $matchedPatientIds = collect($case->matched_patient_ids ?? [])
                ->map(fn ($patientId) => (int) $patientId)
                ->map(fn (int $patientId) => $patientId === $mergedPatientId ? $canonicalPatientId : $patientId)
                ->push($canonicalPatientId)
                ->filter(fn (int $patientId) => $patientId > 0)
                ->unique()
                ->values()
                ->all();

            $matchedBranchIds = collect($case->matched_branch_ids ?? [])
                ->map(fn ($branchId) => (int) $branchId)
                ->filter(fn (int $branchId) => $branchId > 0)
                ->when(
                    $canonicalBranchId !== null,
                    fn (Collection $collection) => $collection->push($canonicalBranchId),
                )
                ->unique()
                ->values()
                ->all();

            $existingReviewNote = trim((string) $case->review_note);
            $autoReviewNote = 'Resolved by merge patient #'.$mergedPatientId.' into #'.$canonicalPatientId;
            if (filled($reason)) {
                $autoReviewNote .= '; reason: '.trim((string) $reason);
            }

            $case->forceFill([
                'patient_id' => $canonicalPatientId,
                'branch_id' => $canonicalBranchId,
                'matched_patient_ids' => $matchedPatientIds,
                'matched_branch_ids' => $matchedBranchIds,
                'status' => MasterPatientDuplicate::STATUS_RESOLVED,
                'review_note' => collect([$existingReviewNote, $autoReviewNote])
                    ->filter(fn (string $line) => trim($line) !== '')
                    ->implode("\n"),
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
            ])->save();

            $resolvedCaseIds[] = $case->id;
        }

        return collect($resolvedCaseIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotPatient(Patient $patient): array
    {
        $snapshot = [];

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $patient->getRawOriginal($field);
        }

        return $snapshot;
    }

    protected function shouldAdoptValue(mixed $canonicalValue, mixed $mergedValue): bool
    {
        if ($mergedValue === null) {
            return false;
        }

        if (is_string($mergedValue) && trim($mergedValue) === '') {
            return false;
        }

        if ($canonicalValue === null) {
            return true;
        }

        if (is_string($canonicalValue) && trim($canonicalValue) === '') {
            return true;
        }

        return false;
    }
}
