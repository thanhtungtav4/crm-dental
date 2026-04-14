<?php

namespace App\Livewire;

use App\Models\PlanItem;
use App\Services\PatientTreatmentPlanDraftService;
use App\Services\PatientTreatmentPlanReadModelService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    public function render(): View
    {
        $sectionData = app(PatientTreatmentPlanReadModelService::class)->sectionData(
            $this->patientId,
            $this->selectedCategoryId,
            $this->procedureSearch,
        );

        return view('livewire.patient-treatment-plan-section', [
            'viewState' => $this->sectionViewState($sectionData),
        ]);
    }

    /**
     * @param  array{
     *     planItems:Collection<int, PlanItem>,
     *     diagnosisMap:Collection<int, mixed>,
     *     diagnosisDetails:array<int, array{tooth_number:string, condition_name:string}>,
     *     diagnosisOptions:array<int, string>,
     *     categories:Collection<int, mixed>,
     *     services:Collection<int, mixed>,
     *     estimatedTotal:float,
     *     discountTotal:float,
     *     totalCost:float,
     *     completedCost:float,
     *     pendingCost:float
     * }  $sectionData
     * @return array{
     *     list_panel:array{
     *         plan_count:int,
     *         plan_rows:array<int, array<string, mixed>>,
     *         summary_panels:array<int, array{
     *             items:array<int, array{label:string, value:string}>
     *         }>
     *     },
     *     plan_modal:array{
     *         is_visible:bool,
     *         diagnosis_options:array<int, string>,
     *         draft_rows:array<int, array<string, mixed>>
     *     },
     *     procedure_modal:array{
     *         is_visible:bool,
     *         selected_category_id:?int,
     *         all_categories_active:bool,
     *         categories:array<int, array{id:int, name:string, is_active:bool}>,
     *         service_rows:array<int, array<string, mixed>>,
     *         settings_url:string
     *     }
     * }
     */
    protected function sectionViewState(array $sectionData): array
    {
        /** @var Collection<int, PlanItem> $planItems */
        $planItems = $sectionData['planItems'];
        /** @var Collection<int, mixed> $diagnosisMap */
        $diagnosisMap = $sectionData['diagnosisMap'];

        return [
            'list_panel' => [
                'plan_count' => $planItems->pluck('treatment_plan_id')->unique()->count(),
                'plan_rows' => $this->planRows($planItems, $diagnosisMap),
                'summary_panels' => [
                    [
                        'items' => [
                            ['label' => 'Chi phí dự kiến', 'value' => $this->formatMoney($sectionData['estimatedTotal'])],
                            ['label' => 'Tiền giảm giá', 'value' => $this->formatMoney($sectionData['discountTotal'])],
                            ['label' => 'Tổng chi phí dự kiến', 'value' => $this->formatMoney($sectionData['totalCost'])],
                        ],
                    ],
                    [
                        'items' => [
                            ['label' => 'Đã hoàn thành', 'value' => $this->formatMoney($sectionData['completedCost'])],
                            ['label' => 'Chưa hoàn thành', 'value' => $this->formatMoney($sectionData['pendingCost'])],
                        ],
                    ],
                ],
            ],
            'plan_modal' => [
                'is_visible' => $this->showPlanModal,
                'diagnosis_options' => $sectionData['diagnosisOptions'],
                'draft_rows' => $this->draftRows($sectionData['diagnosisDetails']),
            ],
            'procedure_modal' => [
                'is_visible' => $this->showProcedureModal,
                'selected_category_id' => $this->selectedCategoryId,
                'all_categories_active' => $this->selectedCategoryId === null,
                'categories' => $this->categoryRows($sectionData['categories']),
                'service_rows' => $this->serviceRows($sectionData['services']),
                'settings_url' => url('/setting/trick'),
            ],
        ];
    }

    /**
     * @param  Collection<int, mixed>  $categories
     * @return array<int, array{id:int, name:string, is_active:bool}>
     */
    protected function categoryRows(Collection $categories): array
    {
        return $categories
            ->map(fn (mixed $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'is_active' => $this->selectedCategoryId === (int) $category->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PlanItem>  $planItems
     * @param  Collection<int, mixed>  $diagnosisMap
     * @return array<int, array<string, mixed>>
     */
    protected function planRows(Collection $planItems, Collection $diagnosisMap): array
    {
        return $planItems
            ->map(function (PlanItem $item) use ($diagnosisMap): array {
                $toothNumbers = $item->tooth_ids ?: ($item->tooth_number ? [$item->tooth_number] : []);
                $diagnosisLabels = collect($item->diagnosis_ids ?? [])
                    ->map(fn (mixed $diagnosisId): ?string => $diagnosisMap[(int) $diagnosisId]->condition?->name ?? null)
                    ->filter()
                    ->join(', ');

                $unitPrice = (float) ($item->price ?? 0);
                $lineAmount = ((int) ($item->quantity ?? 0)) * $unitPrice;
                $discountPercent = (float) ($item->discount_percent ?? 0);
                $discountAmount = (float) ($item->discount_amount ?? 0);
                $vatAmount = (float) ($item->vat_amount ?? 0);

                if ($discountAmount <= 0 && $discountPercent > 0) {
                    $discountAmount = ($discountPercent / 100) * $lineAmount;
                }

                $totalAmount = $item->final_amount !== null
                    ? (float) $item->final_amount
                    : max(0, $lineAmount - $discountAmount + $vatAmount);

                return [
                    'tooth_label' => $toothNumbers !== [] ? implode(' ', $toothNumbers) : '-',
                    'diagnosis_labels' => $diagnosisLabels !== '' ? $diagnosisLabels : '-',
                    'plan_title' => $item->treatmentPlan?->title ?: 'Kế hoạch #'.$item->treatment_plan_id,
                    'plan_url' => $item->treatmentPlan
                        ? route('filament.admin.resources.treatment-plans.edit', [
                            'record' => $item->treatment_plan_id,
                            'return_url' => $this->returnUrl,
                        ])
                        : null,
                    'service_name' => $item->service?->name ?? $item->name,
                    'approval_label' => $item->getApprovalStatusLabel(),
                    'approval_badge_classes' => $this->approvalBadgeClass($item->getApprovalStatusBadgeColor()),
                    'quantity' => (int) ($item->quantity ?? 1),
                    'unit_price_text' => $this->formatMoney($unitPrice),
                    'line_amount_text' => $this->formatMoney($lineAmount),
                    'discount_percent_text' => $this->formatDiscountPercent($discountPercent),
                    'discount_amount_text' => $this->formatMoney($discountAmount),
                    'vat_amount_text' => $this->formatMoney($vatAmount),
                    'total_amount_text' => $this->formatMoney($totalAmount),
                    'notes' => $item->notes ?: '-',
                    'status_label' => $item->getStatusLabel(),
                    'status_badge_classes' => $this->planStatusBadgeClass($item->status),
                    'edit_url' => route('filament.admin.resources.plan-items.edit', [
                        'record' => $item->id,
                        'return_url' => $this->returnUrl,
                        'patient_id' => $this->patientId,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{tooth_number:string, condition_name:string}>  $diagnosisDetails
     * @return array<int, array<string, mixed>>
     */
    protected function draftRows(array $diagnosisDetails): array
    {
        return collect($this->draftItems)
            ->values()
            ->map(function (array $item, int $index) use ($diagnosisDetails): array {
                $quantity = (int) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? 0);
                $lineAmount = $quantity * $price;
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $discountAmount = $discountPercent > 0 ? ($discountPercent / 100) * $lineAmount : 0;
                $totalAmount = $lineAmount - $discountAmount;
                $toothDisplay = collect($item['diagnosis_ids'] ?? [])
                    ->map(fn (mixed $diagnosisId): ?string => $diagnosisDetails[(int) $diagnosisId]['tooth_number'] ?? null)
                    ->filter()
                    ->unique()
                    ->sortBy(fn (string $toothNumber): int => (int) $toothNumber, SORT_NUMERIC)
                    ->values()
                    ->join(', ');

                return [
                    'index' => $index,
                    'tooth_display' => $toothDisplay !== '' ? $toothDisplay : 'Tự động theo tình trạng răng',
                    'service_name' => (string) ($item['service_name'] ?? 'Thủ thuật'),
                    'approval_status' => (string) ($item['approval_status'] ?? PlanItem::APPROVAL_PROPOSED),
                    'line_amount_text' => $this->formatMoney($lineAmount),
                    'discount_amount_text' => $this->formatMoney($discountAmount),
                    'total_amount_text' => $this->formatMoney($totalAmount),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $services
     * @return array<int, array{
     *     id:int,
     *     name:string,
     *     price_text:string,
     *     description:string
     * }>
     */
    protected function serviceRows(Collection $services): array
    {
        return $services
            ->map(fn (mixed $service): array => [
                'id' => (int) $service->id,
                'name' => (string) $service->name,
                'price_text' => $this->formatMoney($service->default_price ?? 0),
                'description' => (string) ($service->description ?: '-'),
            ])
            ->values()
            ->all();
    }

    protected function approvalBadgeClass(string $badgeColor): string
    {
        return match ($badgeColor) {
            'success' => 'is-completed',
            'warning' => 'is-progress',
            'danger' => 'is-cancelled',
            default => 'is-default',
        };
    }

    protected function planStatusBadgeClass(?string $status): string
    {
        return match ($status) {
            PlanItem::STATUS_COMPLETED => 'is-completed',
            PlanItem::STATUS_IN_PROGRESS => 'is-progress',
            PlanItem::STATUS_CANCELLED => 'is-cancelled',
            default => 'is-default',
        };
    }

    protected function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    protected function formatDiscountPercent(float $value): string
    {
        return $value > 0
            ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.')
            : '0';
    }
}
