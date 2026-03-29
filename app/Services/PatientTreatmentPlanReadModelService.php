<?php

namespace App\Services;

use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Support\Collection;

class PatientTreatmentPlanReadModelService
{
    /**
     * @return Collection<int, Service>
     */
    public function servicesByIds(array $serviceIds): Collection
    {
        $normalizedServiceIds = collect($serviceIds)
            ->map(static fn (mixed $serviceId): int => (int) $serviceId)
            ->filter(static fn (int $serviceId): bool => $serviceId > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedServiceIds === []) {
            return collect();
        }

        return Service::query()
            ->whereIn('id', $normalizedServiceIds)
            ->get(['id', 'name', 'default_price']);
    }

    /**
     * @return array{
     *     planItems:Collection<int, PlanItem>,
     *     diagnosisMap:Collection<int, PatientToothCondition>,
     *     diagnosisRecords:Collection<int, PatientToothCondition>,
     *     diagnosisOptions:array<int, string>,
     *     diagnosisDetails:array<int, array{tooth_number:string, condition_name:string}>,
     *     categories:Collection<int, ServiceCategory>,
     *     services:Collection<int, Service>,
     *     estimatedTotal:float,
     *     discountTotal:float,
     *     totalCost:float,
     *     completedCost:float,
     *     pendingCost:float
     * }
     */
    public function sectionData(
        int $patientId,
        ?int $selectedCategoryId = null,
        string $procedureSearch = '',
    ): array {
        $planItems = $this->planItems($patientId);
        $diagnosisMap = $this->diagnosisMap($planItems);
        $diagnosisRecords = $this->diagnosisRecords($patientId);

        return [
            'planItems' => $planItems,
            'diagnosisMap' => $diagnosisMap,
            'diagnosisRecords' => $diagnosisRecords,
            'diagnosisOptions' => $this->diagnosisOptions($diagnosisRecords),
            'diagnosisDetails' => $this->diagnosisDetails($diagnosisRecords),
            'categories' => $this->categories(),
            'services' => $this->services($selectedCategoryId, $procedureSearch),
            ...$this->financialTotals($planItems),
        ];
    }

    /**
     * @return Collection<int, PlanItem>
     */
    public function planItems(int $patientId): Collection
    {
        return PlanItem::query()
            ->with(['service:id,name', 'treatmentPlan:id,patient_id,title'])
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
            ->latest('id')
            ->get();
    }

    /**
     * @param  Collection<int, PlanItem>  $planItems
     * @return Collection<int, PatientToothCondition>
     */
    public function diagnosisMap(Collection $planItems): Collection
    {
        $diagnosisIds = $planItems
            ->pluck('diagnosis_ids')
            ->filter()
            ->flatten()
            ->map(static fn (mixed $diagnosisId): int => (int) $diagnosisId)
            ->filter()
            ->unique()
            ->values();

        if ($diagnosisIds->isEmpty()) {
            return collect();
        }

        return PatientToothCondition::query()
            ->with('condition:id,name')
            ->whereIn('id', $diagnosisIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return Collection<int, PatientToothCondition>
     */
    public function diagnosisRecords(int $patientId): Collection
    {
        return PatientToothCondition::query()
            ->with('condition:id,name')
            ->where('patient_id', $patientId)
            ->where(function ($query): void {
                $query->whereNull('treatment_status')
                    ->orWhereIn('treatment_status', [
                        PatientToothCondition::STATUS_CURRENT,
                        PatientToothCondition::STATUS_IN_TREATMENT,
                    ]);
            })
            ->orderByRaw('CAST(tooth_number AS UNSIGNED) ASC')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $diagnosisRecords
     * @return array<int, string>
     */
    public function diagnosisOptions(Collection $diagnosisRecords): array
    {
        return $diagnosisRecords
            ->mapWithKeys(function (PatientToothCondition $condition): array {
                $label = trim(sprintf(
                    '%s %s',
                    $condition->tooth_number ? 'Răng '.$condition->tooth_number.' -' : '',
                    $condition->condition?->name ?? $condition->tooth_condition_id
                ));

                return [$condition->id => $label];
            })
            ->all();
    }

    /**
     * @param  Collection<int, PatientToothCondition>  $diagnosisRecords
     * @return array<int, array{tooth_number:string, condition_name:string}>
     */
    public function diagnosisDetails(Collection $diagnosisRecords): array
    {
        return $diagnosisRecords
            ->mapWithKeys(fn (PatientToothCondition $condition): array => [
                $condition->id => [
                    'tooth_number' => (string) $condition->tooth_number,
                    'condition_name' => (string) ($condition->condition?->name ?? ''),
                ],
            ])
            ->all();
    }

    /**
     * @return Collection<int, ServiceCategory>
     */
    public function categories(): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'parent_id']);
    }

    /**
     * @return Collection<int, Service>
     */
    public function services(?int $selectedCategoryId = null, string $procedureSearch = ''): Collection
    {
        return Service::query()
            ->active()
            ->when($selectedCategoryId, fn ($query) => $query->where('category_id', $selectedCategoryId))
            ->when($procedureSearch !== '', fn ($query) => $query->where('name', 'like', '%'.$procedureSearch.'%'))
            ->ordered()
            ->limit(200)
            ->get(['id', 'name', 'default_price', 'description']);
    }

    /**
     * @param  Collection<int, PlanItem>  $planItems
     * @return array{
     *     estimatedTotal:float,
     *     discountTotal:float,
     *     totalCost:float,
     *     completedCost:float,
     *     pendingCost:float
     * }
     */
    public function financialTotals(Collection $planItems): array
    {
        $calculateLineAmount = fn (PlanItem $planItem): float => ((float) ($planItem->price ?? 0)) * ((int) ($planItem->quantity ?? 0));
        $calculateDiscountAmount = function (PlanItem $planItem) use ($calculateLineAmount): float {
            $amount = $calculateLineAmount($planItem);
            $discountAmount = (float) ($planItem->discount_amount ?? 0);
            $discountPercent = (float) ($planItem->discount_percent ?? 0);

            if ($discountAmount <= 0 && $discountPercent > 0) {
                $discountAmount = ($discountPercent / 100) * $amount;
            }

            return $discountAmount;
        };
        $calculateFinalAmount = function (PlanItem $planItem) use ($calculateLineAmount, $calculateDiscountAmount): float {
            if ($planItem->final_amount !== null) {
                return (float) $planItem->final_amount;
            }

            $vatAmount = (float) ($planItem->vat_amount ?? 0);

            return max(0, $calculateLineAmount($planItem) - $calculateDiscountAmount($planItem) + $vatAmount);
        };

        $estimatedTotal = (float) $planItems->sum(fn (PlanItem $planItem): float => $calculateLineAmount($planItem));
        $discountTotal = (float) $planItems->sum(fn (PlanItem $planItem): float => $calculateDiscountAmount($planItem));
        $totalCost = (float) $planItems->sum(fn (PlanItem $planItem): float => $calculateFinalAmount($planItem));
        $completedCost = (float) $planItems
            ->filter(fn (PlanItem $planItem): bool => (bool) $planItem->is_completed || $planItem->status === PlanItem::STATUS_COMPLETED)
            ->sum(fn (PlanItem $planItem): float => $calculateFinalAmount($planItem));

        return [
            'estimatedTotal' => $estimatedTotal,
            'discountTotal' => $discountTotal,
            'totalCost' => $totalCost,
            'completedCost' => $completedCost,
            'pendingCost' => max(0, $totalCost - $completedCost),
        ];
    }
}
