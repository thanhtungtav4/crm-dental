<?php

namespace App\Livewire;

use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\PlanItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ToothCondition;
use App\Models\TreatmentPlan;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PatientTreatmentPlanSection extends Component
{
    public int $patientId;

    public bool $showPlanModal = false;

    public bool $showProcedureModal = false;

    public array $draftItems = [];

    public array $selectedServiceIds = [];

    public string $procedureSearch = '';

    public ?int $selectedCategoryId = null;

    public function mount(int $patientId): void
    {
        $this->patientId = $patientId;
    }

    public function openPlanModal(): void
    {
        $this->syncDiagnosisFromLatestExam();
        $this->showPlanModal = true;
    }

    public function closePlanModal(): void
    {
        $this->showPlanModal = false;
        $this->showProcedureModal = false;
    }

    public function openProcedureModal(): void
    {
        $this->showProcedureModal = true;
    }

    public function closeProcedureModal(): void
    {
        $this->showProcedureModal = false;
        $this->selectedServiceIds = [];
    }

    public function selectCategory(?int $categoryId = null): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function addSelectedServices(): void
    {
        if (empty($this->selectedServiceIds)) {
            return;
        }

        $services = Service::query()
            ->whereIn('id', $this->selectedServiceIds)
            ->get(['id', 'name', 'default_price']);

        foreach ($services as $service) {
            $this->draftItems[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'diagnosis_ids' => [],
                'quantity' => 1,
                'price' => (float) ($service->default_price ?? 0),
                'discount_percent' => 0,
                'discount_amount' => 0,
                'notes' => '',
                'approval_status' => PlanItem::APPROVAL_PROPOSED,
                'approval_decline_reason' => '',
            ];
        }

        $this->selectedServiceIds = [];
        $this->showProcedureModal = false;
    }

    public function removeDraftItem(int $index): void
    {
        if (! isset($this->draftItems[$index])) {
            return;
        }

        unset($this->draftItems[$index]);
        $this->draftItems = array_values($this->draftItems);
    }

    public function savePlanItems(): void
    {
        if (empty($this->draftItems)) {
            Notification::make()
                ->title('Vui lòng thêm ít nhất một thủ thuật')
                ->warning()
                ->send();

            return;
        }

        foreach ($this->draftItems as $index => $item) {
            $approvalStatus = $this->normalizeApprovalStatus($item['approval_status'] ?? null);
            $declineReason = trim((string) ($item['approval_decline_reason'] ?? ''));

            if ($approvalStatus === PlanItem::APPROVAL_DECLINED && $declineReason === '') {
                Notification::make()
                    ->title('Cần nhập lý do từ chối cho hạng mục #'.($index + 1))
                    ->warning()
                    ->send();

                return;
            }
        }

        $this->syncDiagnosisFromLatestExam();

        $plan = $this->resolvePlan();
        $allDiagnosisIds = collect($this->draftItems)
            ->pluck('diagnosis_ids')
            ->filter()
            ->flatten()
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        $diagnosisLookup = PatientToothCondition::query()
            ->where('patient_id', $this->patientId)
            ->whereIn('id', $allDiagnosisIds)
            ->get(['id', 'tooth_number'])
            ->keyBy('id');

        foreach ($this->draftItems as $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);
            $serviceName = (string) ($item['service_name'] ?? '');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (float) ($item['price'] ?? 0);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            $discountAmount = (float) ($item['discount_amount'] ?? 0);
            $notes = (string) ($item['notes'] ?? '');
            $approvalStatus = $this->normalizeApprovalStatus($item['approval_status'] ?? null);
            $approvalDeclineReason = trim((string) ($item['approval_decline_reason'] ?? ''));
            if ($approvalStatus !== PlanItem::APPROVAL_DECLINED) {
                $approvalDeclineReason = '';
            }

            $diagnosisIds = collect($item['diagnosis_ids'] ?? [])
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values()
                ->all();

            $toothIds = collect($diagnosisIds)
                ->map(fn (int $diagnosisId) => (string) ($diagnosisLookup->get($diagnosisId)?->tooth_number ?? ''))
                ->filter()
                ->unique()
                ->sortBy(fn (string $toothNumber) => (int) $toothNumber, SORT_NUMERIC)
                ->values()
                ->all();

            $lineAmount = $quantity * $price;
            if ($discountAmount <= 0 && $discountPercent > 0) {
                $discountAmount = ($discountPercent / 100) * $lineAmount;
            }
            $finalAmount = max(0, $lineAmount - $discountAmount);

            PlanItem::create([
                'treatment_plan_id' => $plan->id,
                'service_id' => $serviceId ?: null,
                'name' => $serviceName ?: 'Thủ thuật',
                'tooth_ids' => $toothIds ?: null,
                'tooth_number' => $toothIds ? implode(',', $toothIds) : null,
                'diagnosis_ids' => $diagnosisIds ?: null,
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

        $this->draftItems = [];
        $this->showPlanModal = false;

        Notification::make()
            ->title('Đã lưu kế hoạch điều trị')
            ->success()
            ->send();
    }

    protected function syncDiagnosisFromLatestExam(): void
    {
        $latestClinicalNote = ClinicalNote::query()
            ->where('patient_id', $this->patientId)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['id', 'tooth_diagnosis_data']);

        $diagnosisData = $latestClinicalNote?->tooth_diagnosis_data;

        if (! is_array($diagnosisData)) {
            return;
        }

        $conditionIdByCode = $this->buildConditionCodeMap();
        if (empty($conditionIdByCode)) {
            return;
        }

        DB::transaction(function () use ($diagnosisData, $conditionIdByCode) {
            $teethInChart = [];
            $targetRows = [];

            foreach ($diagnosisData as $toothNumber => $payload) {
                $tooth = trim((string) $toothNumber);
                if ($tooth === '') {
                    continue;
                }

                $teethInChart[$tooth] = true;
                $notes = trim((string) data_get($payload, 'notes', ''));
                $conditions = collect(data_get($payload, 'conditions', []))
                    ->map(fn ($code) => $this->normalizeConditionCode((string) $code))
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($conditions as $conditionCode) {
                    $conditionId = $conditionIdByCode[$conditionCode] ?? null;
                    if (! $conditionId) {
                        continue;
                    }

                    $targetRows[] = [
                        'tooth_number' => $tooth,
                        'tooth_condition_id' => (int) $conditionId,
                        'notes' => $notes !== '' ? $notes : null,
                    ];
                }
            }

            if (empty($teethInChart)) {
                return;
            }

            $existingByKey = PatientToothCondition::withTrashed()
                ->where('patient_id', $this->patientId)
                ->whereIn('tooth_number', array_keys($teethInChart))
                ->get()
                ->keyBy(fn (PatientToothCondition $condition) => $this->makeDiagnosisKey(
                    (string) $condition->tooth_number,
                    (int) $condition->tooth_condition_id
                ));

            $activeDiagnosisKeys = [];

            foreach ($targetRows as $targetRow) {
                $toothNumber = (string) $targetRow['tooth_number'];
                $toothConditionId = (int) $targetRow['tooth_condition_id'];
                $key = $this->makeDiagnosisKey($toothNumber, $toothConditionId);
                $activeDiagnosisKeys[$key] = true;

                /** @var PatientToothCondition|null $existing */
                $existing = $existingByKey->get($key);
                if ($existing) {
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
                    if (empty($existing->diagnosed_at)) {
                        $updates['diagnosed_at'] = now()->toDateString();
                    }
                    if (empty($existing->diagnosed_by) && Auth::id()) {
                        $updates['diagnosed_by'] = Auth::id();
                    }

                    if (! empty($updates)) {
                        $existing->update($updates);
                    }

                    continue;
                }

                PatientToothCondition::create([
                    'patient_id' => $this->patientId,
                    'tooth_number' => $toothNumber,
                    'tooth_condition_id' => $toothConditionId,
                    'treatment_status' => PatientToothCondition::STATUS_CURRENT,
                    'notes' => $targetRow['notes'],
                    'diagnosed_at' => now()->toDateString(),
                    'diagnosed_by' => Auth::id(),
                ]);
            }

            $currentConditions = PatientToothCondition::query()
                ->where('patient_id', $this->patientId)
                ->whereIn('tooth_number', array_keys($teethInChart))
                ->where('treatment_status', PatientToothCondition::STATUS_CURRENT)
                ->get(['id', 'tooth_number', 'tooth_condition_id']);

            foreach ($currentConditions as $condition) {
                $key = $this->makeDiagnosisKey(
                    (string) $condition->tooth_number,
                    (int) $condition->tooth_condition_id
                );

                if (! isset($activeDiagnosisKeys[$key])) {
                    $condition->delete();
                }
            }
        });
    }

    protected function buildConditionCodeMap(): array
    {
        $conditionMap = [];

        ToothCondition::query()
            ->get(['id', 'code', 'name'])
            ->each(function (ToothCondition $condition) use (&$conditionMap) {
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

    protected function makeDiagnosisKey(string $toothNumber, int $toothConditionId): string
    {
        return $toothNumber.'|'.$toothConditionId;
    }

    protected function resolvePlan(): TreatmentPlan
    {
        $plan = TreatmentPlan::query()
            ->where('patient_id', $this->patientId)
            ->latest('id')
            ->first();

        if ($plan) {
            return $plan;
        }

        $patient = Patient::query()->select(['id', 'first_branch_id'])->find($this->patientId);

        return TreatmentPlan::create([
            'patient_id' => $this->patientId,
            'doctor_id' => Auth::id(),
            'branch_id' => $patient?->first_branch_id,
            'title' => 'Kế hoạch điều trị ngày '.now()->format('d/m/Y'),
            'status' => 'draft',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    protected function normalizeApprovalStatus(mixed $status): string
    {
        return PlanItem::normalizeApprovalStatus($status) ?? PlanItem::DEFAULT_APPROVAL_STATUS;
    }

    protected function getPlanItems(): Collection
    {
        return PlanItem::query()
            ->with(['service:id,name', 'treatmentPlan:id,patient_id'])
            ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $this->patientId))
            ->latest('id')
            ->get();
    }

    protected function getDiagnosisMap(Collection $planItems): Collection
    {
        $diagnosisIds = $planItems
            ->pluck('diagnosis_ids')
            ->filter()
            ->flatten()
            ->unique()
            ->values();

        if ($diagnosisIds->isEmpty()) {
            return collect();
        }

        return PatientToothCondition::with('condition:id,name')
            ->whereIn('id', $diagnosisIds)
            ->get()
            ->keyBy('id');
    }

    protected function getDiagnosisRecords(): Collection
    {
        return PatientToothCondition::query()
            ->with('condition:id,name')
            ->where('patient_id', $this->patientId)
            ->where(function ($query) {
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

    protected function getDiagnosisOptions(Collection $diagnosisRecords): array
    {
        return $diagnosisRecords
            ->mapWithKeys(function (PatientToothCondition $condition) {
                $label = trim(sprintf('%s %s',
                    $condition->tooth_number ? 'Răng '.$condition->tooth_number.' -' : '',
                    $condition->condition?->name ?? $condition->tooth_condition_id
                ));

                return [$condition->id => $label];
            })
            ->all();
    }

    protected function getCategories(): Collection
    {
        return ServiceCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'parent_id']);
    }

    protected function getServices(): Collection
    {
        return Service::query()
            ->active()
            ->when($this->selectedCategoryId, fn ($query) => $query->where('category_id', $this->selectedCategoryId))
            ->when($this->procedureSearch, fn ($query) => $query->where('name', 'like', '%'.$this->procedureSearch.'%'))
            ->ordered()
            ->limit(200)
            ->get(['id', 'name', 'default_price', 'description']);
    }

    public function render()
    {
        $planItems = $this->getPlanItems();
        $diagnosisMap = $this->getDiagnosisMap($planItems);
        $diagnosisRecords = $this->getDiagnosisRecords();
        $diagnosisOptions = $this->getDiagnosisOptions($diagnosisRecords);
        $diagnosisDetails = $diagnosisRecords
            ->mapWithKeys(fn (PatientToothCondition $condition) => [
                $condition->id => [
                    'tooth_number' => (string) $condition->tooth_number,
                    'condition_name' => (string) ($condition->condition?->name ?? ''),
                ],
            ])
            ->all();
        $categories = $this->getCategories();
        $services = $this->getServices();

        $calcLineAmount = fn ($item) => ((float) ($item->price ?? 0)) * ((int) ($item->quantity ?? 0));
        $calcDiscountAmount = function ($item) use ($calcLineAmount) {
            $amount = $calcLineAmount($item);
            $discountAmount = (float) ($item->discount_amount ?? 0);
            $discountPercent = (float) ($item->discount_percent ?? 0);

            if ($discountAmount <= 0 && $discountPercent > 0) {
                $discountAmount = ($discountPercent / 100) * $amount;
            }

            return $discountAmount;
        };
        $calcFinalAmount = function ($item) use ($calcLineAmount, $calcDiscountAmount) {
            if ($item->final_amount !== null) {
                return (float) $item->final_amount;
            }

            $vatAmount = (float) ($item->vat_amount ?? 0);

            return max(0, $calcLineAmount($item) - $calcDiscountAmount($item) + $vatAmount);
        };

        $estimatedTotal = $planItems->sum(fn ($item) => $calcLineAmount($item));
        $discountTotal = $planItems->sum(fn ($item) => $calcDiscountAmount($item));
        $totalCost = $planItems->sum(fn ($item) => $calcFinalAmount($item));
        $completedCost = $planItems->filter(fn ($item) => $item->is_completed || $item->status === 'completed')
            ->sum(fn ($item) => $calcFinalAmount($item));
        $pendingCost = max(0, $totalCost - $completedCost);

        return view('livewire.patient-treatment-plan-section', [
            'planItems' => $planItems,
            'diagnosisMap' => $diagnosisMap,
            'diagnosisOptions' => $diagnosisOptions,
            'diagnosisDetails' => $diagnosisDetails,
            'categories' => $categories,
            'services' => $services,
            'estimatedTotal' => $estimatedTotal,
            'discountTotal' => $discountTotal,
            'totalCost' => $totalCost,
            'completedCost' => $completedCost,
            'pendingCost' => $pendingCost,
        ]);
    }
}
