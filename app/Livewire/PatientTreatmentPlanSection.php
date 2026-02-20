<?php

namespace App\Livewire;

use App\Models\PlanItem;
use App\Models\Patient;
use App\Models\PatientToothCondition;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TreatmentPlan;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
                'tooth_text' => '',
                'diagnosis_ids' => [],
                'quantity' => 1,
                'price' => (float) ($service->default_price ?? 0),
                'discount_percent' => 0,
                'discount_amount' => 0,
                'notes' => '',
                'patient_approved' => false,
            ];
        }

        $this->selectedServiceIds = [];
        $this->showProcedureModal = false;
    }

    public function removeDraftItem(int $index): void
    {
        if (!isset($this->draftItems[$index])) {
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

        $plan = $this->resolvePlan();

        foreach ($this->draftItems as $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);
            $serviceName = (string) ($item['service_name'] ?? '');
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = (float) ($item['price'] ?? 0);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            $discountAmount = (float) ($item['discount_amount'] ?? 0);
            $notes = (string) ($item['notes'] ?? '');
            $patientApproved = (bool) ($item['patient_approved'] ?? false);

            $toothText = (string) ($item['tooth_text'] ?? '');
            $toothIds = collect(preg_split('/[,\s]+/', $toothText))
                ->filter()
                ->values()
                ->all();

            $diagnosisIds = collect($item['diagnosis_ids'] ?? [])
                ->map(fn($value) => (int) $value)
                ->filter()
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
                'patient_approved' => $patientApproved,
                'status' => $patientApproved ? 'in_progress' : 'pending',
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
            'title' => 'Kế hoạch điều trị ngày ' . now()->format('d/m/Y'),
            'status' => 'draft',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    protected function getPlanItems(): Collection
    {
        return PlanItem::query()
            ->with(['service:id,name', 'treatmentPlan:id,patient_id'])
            ->whereHas('treatmentPlan', fn($query) => $query->where('patient_id', $this->patientId))
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

    protected function getDiagnosisOptions(): array
    {
        return PatientToothCondition::with('condition:id,name')
            ->where('patient_id', $this->patientId)
            ->get()
            ->mapWithKeys(function (PatientToothCondition $condition) {
                $label = trim(sprintf('%s %s',
                    $condition->tooth_number ? 'Răng ' . $condition->tooth_number . ' -' : '',
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
            ->when($this->selectedCategoryId, fn($query) => $query->where('category_id', $this->selectedCategoryId))
            ->when($this->procedureSearch, fn($query) => $query->where('name', 'like', '%' . $this->procedureSearch . '%'))
            ->ordered()
            ->limit(200)
            ->get(['id', 'name', 'default_price', 'description']);
    }

    public function render()
    {
        $planItems = $this->getPlanItems();
        $diagnosisMap = $this->getDiagnosisMap($planItems);
        $diagnosisOptions = $this->getDiagnosisOptions();
        $categories = $this->getCategories();
        $services = $this->getServices();

        $calcLineAmount = fn($item) => ((float) ($item->price ?? 0)) * ((int) ($item->quantity ?? 0));
        $calcDiscountAmount = function ($item) use ($calcLineAmount) {
            $amount = $calcLineAmount($item);
            $discountAmount = (float) ($item->discount_amount ?? 0);
            $discountPercent = (float) ($item->discount_percent ?? 0);

            if ($discountAmount <= 0 && $discountPercent > 0) {
                $discountAmount = ($discountPercent / 100) * $amount;
            }

            return $discountAmount;
        };

        $estimatedTotal = $planItems->sum(fn($item) => $calcLineAmount($item));
        $discountTotal = $planItems->sum(fn($item) => $calcDiscountAmount($item));
        $totalCost = $estimatedTotal - $discountTotal;
        $completedCost = $planItems->filter(fn($item) => $item->is_completed || $item->status === 'completed')
            ->sum(fn($item) => $calcLineAmount($item) - $calcDiscountAmount($item));
        $pendingCost = max(0, $totalCost - $completedCost);

        return view('livewire.patient-treatment-plan-section', [
            'planItems' => $planItems,
            'diagnosisMap' => $diagnosisMap,
            'diagnosisOptions' => $diagnosisOptions,
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
