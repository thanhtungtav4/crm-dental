<?php

namespace App\Livewire;

use App\Models\PlanItem;
use App\Services\PatientTreatmentPlanDraftService;
use App\Services\PatientTreatmentPlanReadModelService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PatientTreatmentPlanSection extends Component
{
    public int $patientId;

    public string $returnUrl = '';

    public bool $showPlanModal = false;

    public bool $showProcedureModal = false;

    public array $draftItems = [];

    public array $selectedServiceIds = [];

    public string $procedureSearch = '';

    public ?int $selectedCategoryId = null;

    public function mount(int $patientId): void
    {
        $this->patientId = $patientId;
        $this->returnUrl = request()->fullUrl();
    }

    public function openPlanModal(): void
    {
        app(PatientTreatmentPlanDraftService::class)->prepareDraft($this->patientId, Auth::id());
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

        $services = app(PatientTreatmentPlanReadModelService::class)
            ->servicesByIds($this->selectedServiceIds);

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

        app(PatientTreatmentPlanDraftService::class)->saveDraftItems(
            patientId: $this->patientId,
            draftItems: $this->draftItems,
            actorId: Auth::id(),
        );

        $this->draftItems = [];
        $this->showPlanModal = false;

        Notification::make()
            ->title('Đã lưu kế hoạch điều trị')
            ->success()
            ->send();
    }

    protected function normalizeApprovalStatus(mixed $status): string
    {
        return PlanItem::normalizeApprovalStatus($status) ?? PlanItem::DEFAULT_APPROVAL_STATUS;
    }

    public function render()
    {
        $sectionData = app(PatientTreatmentPlanReadModelService::class)->sectionData(
            $this->patientId,
            $this->selectedCategoryId,
            $this->procedureSearch,
        );

        return view('livewire.patient-treatment-plan-section', [
            ...$sectionData,
        ]);
    }
}
