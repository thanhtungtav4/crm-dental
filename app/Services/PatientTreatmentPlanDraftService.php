<?php

namespace App\Services;

use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientTreatmentPlanDraftService
{
    public function prepareDraft(int $patientId, ?int $actorId): void
    {
        $latestClinicalNote = ClinicalNote::query()
            ->where('patient_id', $patientId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['id', 'tooth_diagnosis_data']);

        $diagnosisData = $latestClinicalNote?->tooth_diagnosis_data;

        if (! is_array($diagnosisData)) {
            return;
        }

        $conditionIdByCode = $this->conditionCodeMap();
        if ($conditionIdByCode === []) {
            return;
        }

        DB::transaction(function () use ($patientId, $actorId, $diagnosisData, $conditionIdByCode): void {
            $teethInChart = [];
            $targetRows = [];

            foreach ($diagnosisData as $toothNumber => $payload) {
                $normalizedToothNumber = trim((string) $toothNumber);
                if ($normalizedToothNumber === '') {
                    continue;
                }

                $teethInChart[$normalizedToothNumber] = true;
                $notes = trim((string) data_get($payload, 'notes', ''));
                $conditions = collect(data_get($payload, 'conditions', []))
                    ->map(fn (mixed $code): string => $this->normalizeConditionCode((string) $code))
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($conditions as $conditionCode) {
                    $conditionId = $conditionIdByCode[$conditionCode] ?? null;
                    if (! $conditionId) {
                        continue;
                    }

                    $targetRows[] = [
                        'tooth_number' => $normalizedToothNumber,
                        'tooth_condition_id' => (int) $conditionId,
                        'notes' => $notes !== '' ? $notes : null,
                    ];
                }
            }

            if ($teethInChart === []) {
                return;
            }

            $existingByKey = PatientToothCondition::withTrashed()
                ->where('patient_id', $patientId)
                ->whereIn('tooth_number', array_keys($teethInChart))
                ->get()
                ->keyBy(fn (PatientToothCondition $condition): string => $this->diagnosisKey(
                    (string) $condition->tooth_number,
                    (int) $condition->tooth_condition_id,
                ));

            $activeDiagnosisKeys = [];

            foreach ($targetRows as $targetRow) {
                $diagnosisKey = $this->diagnosisKey(
                    $targetRow['tooth_number'],
                    $targetRow['tooth_condition_id'],
                );
                $activeDiagnosisKeys[$diagnosisKey] = true;

                /** @var PatientToothCondition|null $existing */
                $existing = $existingByKey->get($diagnosisKey);
                if ($existing instanceof PatientToothCondition) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }

                    $updates = [];
                    if ($existing->treatment_status === null || $existing->treatment_status === '') {
                        $updates['treatment_status'] = PatientToothCondition::STATUS_CURRENT;
                    }
                    if ($existing->treatment_status === PatientToothCondition::STATUS_CURRENT
                        && $existing->notes !== $targetRow['notes']) {
                        $updates['notes'] = $targetRow['notes'];
                    }
                    if (blank($existing->diagnosed_at)) {
                        $updates['diagnosed_at'] = now()->toDateString();
                    }
                    if (blank($existing->diagnosed_by) && $actorId !== null) {
                        $updates['diagnosed_by'] = $actorId;
                    }

                    if ($updates !== []) {
                        $existing->update($updates);
                    }

                    continue;
                }

                PatientToothCondition::create([
                    'patient_id' => $patientId,
                    'tooth_number' => $targetRow['tooth_number'],
                    'tooth_condition_id' => $targetRow['tooth_condition_id'],
                    'treatment_status' => PatientToothCondition::STATUS_CURRENT,
                    'notes' => $targetRow['notes'],
                    'diagnosed_at' => now()->toDateString(),
                    'diagnosed_by' => $actorId,
                ]);
            }

            $currentConditions = PatientToothCondition::query()
                ->where('patient_id', $patientId)
                ->whereIn('tooth_number', array_keys($teethInChart))
                ->where('treatment_status', PatientToothCondition::STATUS_CURRENT)
                ->get(['id', 'tooth_number', 'tooth_condition_id']);

            foreach ($currentConditions as $condition) {
                $diagnosisKey = $this->diagnosisKey(
                    (string) $condition->tooth_number,
                    (int) $condition->tooth_condition_id,
                );

                if (! isset($activeDiagnosisKeys[$diagnosisKey])) {
                    $condition->delete();
                }
            }
        });
    }

    /**
     * @param  array<int, array{
     *     service_id:mixed,
     *     service_name:mixed,
     *     diagnosis_ids:mixed,
     *     quantity:mixed,
     *     price:mixed,
     *     discount_percent:mixed,
     *     discount_amount:mixed,
     *     notes:mixed,
     *     approval_status:mixed,
     *     approval_decline_reason:mixed
     * }>  $draftItems
     */
    public function saveDraftItems(int $patientId, array $draftItems, ?int $actorId): TreatmentPlan
    {
        $this->prepareDraft($patientId, $actorId);

        return DB::transaction(function () use ($patientId, $draftItems, $actorId): TreatmentPlan {
            $plan = $this->resolvePlan($patientId, $actorId);
            $diagnosisLookup = $this->diagnosisLookup($patientId, $draftItems);

            foreach ($draftItems as $item) {
                $serviceId = (int) ($item['service_id'] ?? 0);
                $serviceName = (string) ($item['service_name'] ?? '');
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $price = (float) ($item['price'] ?? 0);
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $discountAmount = (float) ($item['discount_amount'] ?? 0);
                $notes = (string) ($item['notes'] ?? '');
                $approvalStatus = PlanItem::normalizeApprovalStatus($item['approval_status'] ?? null)
                    ?? PlanItem::DEFAULT_APPROVAL_STATUS;
                $approvalDeclineReason = trim((string) ($item['approval_decline_reason'] ?? ''));

                if ($approvalStatus !== PlanItem::APPROVAL_DECLINED) {
                    $approvalDeclineReason = '';
                }

                $diagnosisIds = collect($item['diagnosis_ids'] ?? [])
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->filter()
                    ->values()
                    ->all();

                $toothIds = collect($diagnosisIds)
                    ->map(fn (int $diagnosisId): string => (string) ($diagnosisLookup->get($diagnosisId)?->tooth_number ?? ''))
                    ->filter()
                    ->unique()
                    ->sortBy(fn (string $toothNumber): int => (int) $toothNumber, SORT_NUMERIC)
                    ->values()
                    ->all();

                $lineAmount = $quantity * $price;
                if ($discountAmount <= 0 && $discountPercent > 0) {
                    $discountAmount = ($discountPercent / 100) * $lineAmount;
                }

                $finalAmount = max(0, $lineAmount - $discountAmount);

                PlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'service_id' => $serviceId > 0 ? $serviceId : null,
                    'name' => $serviceName !== '' ? $serviceName : 'Thủ thuật',
                    'tooth_ids' => $toothIds !== [] ? $toothIds : null,
                    'tooth_number' => $toothIds !== [] ? implode(',', $toothIds) : null,
                    'diagnosis_ids' => $diagnosisIds !== [] ? $diagnosisIds : null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'estimated_cost' => $finalAmount,
                    'actual_cost' => 0,
                    'patient_approved' => $approvalStatus === PlanItem::APPROVAL_APPROVED,
                    'approval_status' => $approvalStatus,
                    'approval_decline_reason' => $approvalDeclineReason !== '' ? $approvalDeclineReason : null,
                    'status' => PlanItem::STATUS_PENDING,
                    'notes' => $notes,
                ]);
            }

            return $plan;
        });
    }

    /**
     * @param  array<int, array{diagnosis_ids:mixed}>  $draftItems
     * @return Collection<int, PatientToothCondition>
     */
    protected function diagnosisLookup(int $patientId, array $draftItems): Collection
    {
        $allDiagnosisIds = collect($draftItems)
            ->pluck('diagnosis_ids')
            ->filter()
            ->flatten()
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($allDiagnosisIds === []) {
            return collect();
        }

        return PatientToothCondition::query()
            ->where('patient_id', $patientId)
            ->whereIn('id', $allDiagnosisIds)
            ->get(['id', 'tooth_number'])
            ->keyBy('id');
    }

    protected function resolvePlan(int $patientId, ?int $actorId): TreatmentPlan
    {
        $plan = TreatmentPlan::query()
            ->where('patient_id', $patientId)
            ->latest('id')
            ->first();

        if ($plan instanceof TreatmentPlan) {
            return $plan;
        }

        $patient = Patient::query()
            ->select(['id', 'first_branch_id'])
            ->find($patientId);

        return TreatmentPlan::create([
            'patient_id' => $patientId,
            'doctor_id' => $actorId,
            'branch_id' => $patient?->first_branch_id,
            'title' => 'Kế hoạch điều trị ngày '.now()->format('d/m/Y'),
            'status' => TreatmentPlan::STATUS_DRAFT,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function conditionCodeMap(): array
    {
        $conditionMap = [];

        ToothCondition::query()
            ->get(['id', 'code', 'name'])
            ->each(function (ToothCondition $condition) use (&$conditionMap): void {
                $codes = [];
                $baseCode = $this->normalizeConditionCode((string) $condition->code);
                if ($baseCode !== '') {
                    $codes[] = $baseCode;
                }

                if (preg_match('/^\(([^)]+)\)/', (string) $condition->name, $matches)) {
                    $nameCode = $this->normalizeConditionCode((string) $matches[1]);
                    if ($nameCode !== '') {
                        $codes[] = $nameCode;
                    }
                }

                if (in_array('KHAC', $codes, true) || in_array('*', $codes, true)) {
                    $codes[] = 'KHAC';
                    $codes[] = '*';
                }

                foreach (array_unique($codes) as $code) {
                    $conditionMap[$code] = (int) $condition->id;
                }
            });

        return $conditionMap;
    }

    protected function normalizeConditionCode(string $code): string
    {
        $normalized = strtoupper(trim($code));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
        $normalized = trim($normalized, '()');

        if ($normalized === 'KHAC' || $normalized === 'KHÁC') {
            return 'KHAC';
        }

        return $normalized;
    }

    protected function diagnosisKey(string $toothNumber, int $toothConditionId): string
    {
        return $toothNumber.'|'.$toothConditionId;
    }
}
