<?php

namespace App\Services;

use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientIdentity;
use App\Models\MasterPatientMerge;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;
use App\Support\PatientIdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MasterPatientIndexService
{
    public function __construct(protected MasterPatientDuplicateWorkflowService $duplicateWorkflowService) {}

    public function syncForPatient(Patient $patient, bool $persist = true): int
    {
        $activeMerge = $this->activeMergeForPatient($patient->id);

        if ($activeMerge) {
            if ($persist) {
                MasterPatientIdentity::query()
                    ->where('patient_id', $patient->id)
                    ->delete();

                $this->ignoreOpenDuplicateCases(
                    MasterPatientDuplicate::query()
                        ->where('status', MasterPatientDuplicate::STATUS_OPEN)
                        ->where(function ($query) use ($patient): void {
                            $query->where('patient_id', $patient->id)
                                ->orWhereJsonContains('matched_patient_ids', $patient->id);
                        }),
                    note: 'Auto-ignore do patient đã được merge vào hồ sơ chính #'.$activeMerge->canonical_patient_id,
                    trigger: 'active_merge_sync',
                );
            }

            return 0;
        }

        $identities = $this->extractIdentities($patient);

        if (! $persist) {
            return count($identities);
        }

        $identityHashes = collect($identities)->pluck('identity_hash')->all();

        MasterPatientIdentity::query()
            ->where('patient_id', $patient->id)
            ->when($identityHashes !== [], fn ($query) => $query->whereNotIn('identity_hash', $identityHashes))
            ->delete();

        foreach ($identities as $identity) {
            MasterPatientIdentity::query()->updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'identity_type' => $identity['identity_type'],
                    'identity_hash' => $identity['identity_hash'],
                ],
                [
                    'branch_id' => $patient->first_branch_id,
                    'identity_value' => $identity['identity_value'],
                    'is_primary' => (bool) $identity['is_primary'],
                    'confidence_score' => $identity['confidence_score'],
                ],
            );
        }

        $this->syncDuplicateCases($patient, $identities);

        return count($identities);
    }

    public function removeForPatient(int $patientId): void
    {
        MasterPatientIdentity::query()
            ->where('patient_id', $patientId)
            ->delete();

        $this->ignoreOpenDuplicateCases(
            MasterPatientDuplicate::query()
                ->where('status', MasterPatientDuplicate::STATUS_OPEN)
                ->where(function ($query) use ($patientId): void {
                    $query->where('patient_id', $patientId)
                        ->orWhereJsonContains('matched_patient_ids', $patientId);
                }),
            note: 'Auto-ignore do bệnh nhân đã bị gỡ khỏi MPI.',
            trigger: 'patient_removed',
        );
    }

    public function hasCrossBranchDuplicate(Patient $patient): bool
    {
        $identityHashes = collect($this->extractIdentities($patient))
            ->pluck('identity_hash')
            ->all();

        if ($identityHashes === []) {
            return false;
        }

        $matchedBranchIds = MasterPatientIdentity::query()
            ->whereIn('identity_hash', $identityHashes)
            ->where('patient_id', '!=', $patient->id)
            ->pluck('branch_id')
            ->push($patient->first_branch_id)
            ->filter(fn ($branchId): bool => is_numeric($branchId) && (int) $branchId > 0)
            ->map(fn ($branchId): int => (int) $branchId)
            ->unique()
            ->values();

        return $matchedBranchIds->count() > 1;
    }

    /**
     * @return Collection<int, object>
     */
    public function duplicateGroups(?string $identityType = null): Collection
    {
        return MasterPatientIdentity::query()
            ->selectRaw(
                'identity_type, identity_hash, MIN(identity_value) as identity_value, COUNT(DISTINCT patient_id) as patient_count, COUNT(DISTINCT branch_id) as branch_count'
            )
            ->when($identityType !== null, fn ($query) => $query->where('identity_type', $identityType))
            ->groupBy('identity_type', 'identity_hash')
            ->havingRaw('COUNT(DISTINCT patient_id) > 1')
            ->havingRaw('COUNT(DISTINCT branch_id) > 1')
            ->orderByDesc('patient_count')
            ->get();
    }

    /**
     * @return Collection<int, MasterPatientDuplicate>
     */
    public function openDuplicateCases(?string $identityType = null): Collection
    {
        return MasterPatientDuplicate::query()
            ->where('status', MasterPatientDuplicate::STATUS_OPEN)
            ->when($identityType !== null, fn ($query) => $query->where('identity_type', $identityType))
            ->orderByDesc('confidence_score')
            ->latest('id')
            ->get();
    }

    /**
     * @return array<int, array{identity_type:string,identity_hash:string,identity_value:string,is_primary:bool,confidence_score:float}>
     */
    protected function extractIdentities(Patient $patient): array
    {
        $identityMap = [];

        $phone = $this->normalizePhone($patient->phone);
        if ($phone !== null) {
            $identityMap['phone'] = [
                'identity_type' => MasterPatientIdentity::TYPE_PHONE,
                'identity_hash' => $this->hashIdentity(MasterPatientIdentity::TYPE_PHONE, $phone),
                'identity_value' => $phone,
                'is_primary' => true,
                'confidence_score' => 95.0,
            ];
        }

        $email = $this->normalizeEmail($patient->email);
        if ($email !== null) {
            $identityMap['email'] = [
                'identity_type' => MasterPatientIdentity::TYPE_EMAIL,
                'identity_hash' => $this->hashIdentity(MasterPatientIdentity::TYPE_EMAIL, $email),
                'identity_value' => $email,
                'is_primary' => false,
                'confidence_score' => 90.0,
            ];
        }

        $cccd = $this->normalizeCccd($patient->cccd);
        if ($cccd !== null) {
            $identityMap['cccd'] = [
                'identity_type' => MasterPatientIdentity::TYPE_CCCD,
                'identity_hash' => $this->hashIdentity(MasterPatientIdentity::TYPE_CCCD, $cccd),
                'identity_value' => $cccd,
                'is_primary' => true,
                'confidence_score' => 99.0,
            ];
        }

        return array_values($identityMap);
    }

    protected function normalizePhone(?string $phone): ?string
    {
        return PatientIdentityNormalizer::normalizePhone($phone);
    }

    protected function normalizeEmail(?string $email): ?string
    {
        return PatientIdentityNormalizer::normalizeEmail($email);
    }

    protected function normalizeCccd(?string $cccd): ?string
    {
        return PatientIdentityNormalizer::normalizeCccd($cccd);
    }

    protected function hashIdentity(string $type, string $value): string
    {
        return PatientIdentityNormalizer::identityHash($type, $value) ?? '';
    }

    protected function activeMergeForPatient(int $patientId): ?MasterPatientMerge
    {
        return MasterPatientMerge::query()
            ->where('merged_patient_id', $patientId)
            ->where('status', MasterPatientMerge::STATUS_APPLIED)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, array{identity_type:string,identity_hash:string,identity_value:string,is_primary:bool,confidence_score:float}>  $identities
     */
    protected function syncDuplicateCases(Patient $patient, array $identities): void
    {
        if ($identities === []) {
            return;
        }

        $minimumConfidence = ClinicRuntimeSettings::mpiDedupeMinConfidence();

        $trackedIdentityHashes = collect($identities)->pluck('identity_hash')->all();

        $this->ignoreOpenDuplicateCases(
            MasterPatientDuplicate::query()
                ->where('patient_id', $patient->id)
                ->where('status', MasterPatientDuplicate::STATUS_OPEN)
                ->when(
                    $trackedIdentityHashes !== [],
                    fn ($query) => $query->whereNotIn('identity_hash', $trackedIdentityHashes)
                ),
            note: 'Auto-ignore do định danh không còn khớp.',
            trigger: 'identity_hash_pruned',
        );

        foreach ($identities as $identity) {
            if ((float) $identity['confidence_score'] < $minimumConfidence) {
                continue;
            }

            $matchedIdentities = MasterPatientIdentity::query()
                ->where('identity_type', $identity['identity_type'])
                ->where('identity_hash', $identity['identity_hash'])
                ->where('patient_id', '!=', $patient->id)
                ->get(['patient_id', 'branch_id', 'confidence_score']);

            if ($matchedIdentities->isEmpty()) {
                $this->ignoreOpenDuplicateCases(
                    MasterPatientDuplicate::query()
                        ->where('identity_type', $identity['identity_type'])
                        ->where('identity_hash', $identity['identity_hash'])
                        ->where('status', MasterPatientDuplicate::STATUS_OPEN),
                    note: 'Auto-ignore do không còn trùng liên chi nhánh.',
                    trigger: 'cross_branch_duplicate_cleared',
                );

                continue;
            }

            $matchedPatientIds = collect([$patient->id])
                ->merge($matchedIdentities->pluck('patient_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $matchedBranchIds = collect([$patient->first_branch_id])
                ->merge($matchedIdentities->pluck('branch_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (count($matchedBranchIds) <= 1) {
                $this->ignoreOpenDuplicateCases(
                    MasterPatientDuplicate::query()
                        ->where('identity_type', $identity['identity_type'])
                        ->where('identity_hash', $identity['identity_hash'])
                        ->where('status', MasterPatientDuplicate::STATUS_OPEN),
                    note: 'Auto-ignore do chỉ trùng trong cùng một chi nhánh.',
                    trigger: 'same_branch_duplicate_cleared',
                );

                continue;
            }

            $maxConfidence = max(
                (float) $identity['confidence_score'],
                (float) ($matchedIdentities->max('confidence_score') ?? 0),
            );

            MasterPatientDuplicate::query()->updateOrCreate(
                [
                    'identity_type' => $identity['identity_type'],
                    'identity_hash' => $identity['identity_hash'],
                    'status' => MasterPatientDuplicate::STATUS_OPEN,
                ],
                [
                    'patient_id' => $patient->id,
                    'branch_id' => $patient->first_branch_id,
                    'identity_value' => $identity['identity_value'],
                    'matched_patient_ids' => $matchedPatientIds,
                    'matched_branch_ids' => $matchedBranchIds,
                    'confidence_score' => $maxConfidence,
                    'review_note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'metadata' => [
                        'patient_count' => count($matchedPatientIds),
                        'branch_count' => count($matchedBranchIds),
                    ],
                ],
            );
        }
    }

    protected function ignoreOpenDuplicateCases(Builder $query, string $note, string $trigger): void
    {
        $query->get()->each(function (MasterPatientDuplicate $duplicateCase) use ($note, $trigger): void {
            $this->duplicateWorkflowService->autoIgnore(
                duplicateCase: $duplicateCase,
                note: $note,
                trigger: $trigger,
            );
        });
    }
}
