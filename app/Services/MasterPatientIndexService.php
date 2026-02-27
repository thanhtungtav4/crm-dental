<?php

namespace App\Services;

use App\Models\MasterPatientDuplicate;
use App\Models\MasterPatientIdentity;
use App\Models\Patient;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MasterPatientIndexService
{
    public function syncForPatient(Patient $patient, bool $persist = true): int
    {
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

        MasterPatientDuplicate::query()
            ->where('status', MasterPatientDuplicate::STATUS_OPEN)
            ->where(function ($query) use ($patientId): void {
                $query->where('patient_id', $patientId)
                    ->orWhereJsonContains('matched_patient_ids', $patientId);
            })
            ->update([
                'status' => MasterPatientDuplicate::STATUS_IGNORED,
                'review_note' => 'Auto-ignore do bệnh nhân đã bị gỡ khỏi MPI.',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
    }

    public function hasCrossBranchDuplicate(Patient $patient): bool
    {
        $identityHashes = collect($this->extractIdentities($patient))
            ->pluck('identity_hash')
            ->all();

        if ($identityHashes === []) {
            return false;
        }

        return MasterPatientIdentity::query()
            ->whereIn('identity_hash', $identityHashes)
            ->where('patient_id', '!=', $patient->id)
            ->exists();
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
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '84')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    protected function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeCccd(?string $cccd): ?string
    {
        if ($cccd === null || trim($cccd) === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', strtoupper(trim($cccd))) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    protected function hashIdentity(string $type, string $value): string
    {
        return hash('sha256', $type.'|'.$value);
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

        MasterPatientDuplicate::query()
            ->where('patient_id', $patient->id)
            ->where('status', MasterPatientDuplicate::STATUS_OPEN)
            ->when(
                $trackedIdentityHashes !== [],
                fn ($query) => $query->whereNotIn('identity_hash', $trackedIdentityHashes)
            )
            ->update([
                'status' => MasterPatientDuplicate::STATUS_IGNORED,
                'review_note' => 'Auto-ignore do định danh không còn khớp.',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

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
                MasterPatientDuplicate::query()
                    ->where('identity_type', $identity['identity_type'])
                    ->where('identity_hash', $identity['identity_hash'])
                    ->where('status', MasterPatientDuplicate::STATUS_OPEN)
                    ->update([
                        'status' => MasterPatientDuplicate::STATUS_IGNORED,
                        'review_note' => 'Auto-ignore do không còn trùng liên chi nhánh.',
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);

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
}
