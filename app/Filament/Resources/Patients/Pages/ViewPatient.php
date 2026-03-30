<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Patients\RelationManagers\PatientPaymentsRelationManager;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;
use App\Services\PhiAccessAuditService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public string $activeTab = 'basic-info';

    public string $workspaceReturnUrl = '';

    protected ?array $cachedTabCounters = null;

    protected ?User $cachedAuthUser = null;

    protected ?array $cachedIdentityHeader = null;

    protected ?array $cachedBasicInfoGrid = null;

    protected ?array $cachedBasicInfoPanels = null;

    protected ?array $cachedRenderedTabs = null;

    protected ?Collection $cachedTreatmentProgress = null;

    protected ?Collection $cachedTreatmentProgressDaySummaries = null;

    protected ?array $cachedTreatmentProgressPanel = null;

    protected ?Collection $cachedMaterialUsages = null;

    protected ?array $cachedLabMaterialsPanel = null;

    protected ?array $cachedLabMaterialSections = null;

    protected ?Collection $cachedFactoryOrders = null;

    protected ?Collection $cachedMaterialIssueNotes = null;

    protected ?array $cachedPaymentPanel = null;

    protected ?array $cachedRenderedPaymentBlocks = null;

    protected ?array $cachedFormsPanel = null;

    protected ?array $cachedRenderedFormSections = null;

    /**
     * Tabs rendered in the custom patient workspace view.
     */
    protected array $workspaceTabs = [
        'basic-info',
        'exam-treatment',
        'prescriptions',
        'photos',
        'lab-materials',
        'appointments',
        'payments',
        'forms',
        'care',
        'activity-log',
    ];

    protected array $legacyTabMap = [
        'overview' => 'basic-info',
        'invoices' => 'payments',
        'notes' => 'care',
        'payment' => 'payments',
        'appointment' => 'appointments',
        'photo' => 'photos',
        'examAndTreatment' => 'exam-treatment',
    ];

    public function mount($record): void
    {
        parent::mount($record);

        $requestedTab = (string) request()->query('tab', 'basic-info');
        $requestedTab = $this->legacyTabMap[$requestedTab] ?? $requestedTab;

        $visibleTabs = $this->visibleWorkspaceTabIds();
        $this->activeTab = in_array($requestedTab, $visibleTabs, true)
            ? $requestedTab
            : ($visibleTabs[0] ?? 'basic-info');
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($this->activeTab);

        if ($this->activeTab === 'exam-treatment') {
            app(PhiAccessAuditService::class)->recordPatientWorkspaceRead(
                patient: $this->record,
                context: [
                    'tab' => 'exam-treatment',
                    'route' => request()->path(),
                ],
            );
        }
    }

    public function getView(): string
    {
        return 'filament.resources.patients.pages.view-patient';
    }

    public function getTitle(): string
    {
        return 'Hồ sơ: '.$this->record->full_name;
    }

    public function getTabsProperty(): array
    {
        return app(PatientOverviewReadModelService::class)->workspaceTabs($this->record, $this->currentUser());
    }

    public function getTabCountersProperty(): array
    {
        if ($this->cachedTabCounters !== null) {
            return $this->cachedTabCounters;
        }

        $this->cachedTabCounters = app(PatientOverviewReadModelService::class)->tabCounters($this->record);

        return $this->cachedTabCounters;
    }

    public function getIdentityHeaderProperty(): array
    {
        if ($this->cachedIdentityHeader !== null) {
            return $this->cachedIdentityHeader;
        }

        $this->cachedIdentityHeader = app(PatientOverviewReadModelService::class)->identityHeaderPayload($this->record);

        return $this->cachedIdentityHeader;
    }

    public function getBasicInfoGridProperty(): array
    {
        if ($this->cachedBasicInfoGrid !== null) {
            return $this->cachedBasicInfoGrid;
        }

        $this->cachedBasicInfoGrid = app(PatientOverviewReadModelService::class)->basicInfoGridPayload($this->record);

        return $this->cachedBasicInfoGrid;
    }

    public function getBasicInfoPanelsProperty(): array
    {
        if ($this->cachedBasicInfoPanels !== null) {
            return $this->cachedBasicInfoPanels;
        }

        $this->cachedBasicInfoPanels = app(PatientOverviewReadModelService::class)->basicInfoPanelsPayload();

        return $this->cachedBasicInfoPanels;
    }

    public function getRenderedTabsProperty(): array
    {
        if ($this->cachedRenderedTabs !== null) {
            return $this->cachedRenderedTabs;
        }

        $this->cachedRenderedTabs = collect($this->tabs)
            ->map(function (array $tab): array {
                $isActive = $this->activeTab === $tab['id'];

                return [
                    ...$tab,
                    'button_id' => 'patient-workspace-tab-'.$tab['id'],
                    'panel_id' => 'patient-workspace-panel-'.$tab['id'],
                    'aria_selected' => $isActive ? 'true' : 'false',
                    'tabindex' => $isActive ? '0' : '-1',
                    'button_class' => $isActive ? 'crm-top-tab is-active' : 'crm-top-tab',
                ];
            })
            ->all();

        return $this->cachedRenderedTabs;
    }

    public function getActivePanelIdProperty(): string
    {
        return 'patient-workspace-panel-'.$this->activeTab;
    }

    public function getActiveTabButtonIdProperty(): string
    {
        return 'patient-workspace-tab-'.$this->activeTab;
    }

    public function getTreatmentProgressProperty(): Collection
    {
        if ($this->cachedTreatmentProgress !== null) {
            return $this->cachedTreatmentProgress;
        }

        $this->cachedTreatmentProgress = app(PatientOverviewReadModelService::class)
            ->treatmentProgress($this->record, $this->workspaceReturnUrl);

        return $this->cachedTreatmentProgress;
    }

    public function getTreatmentProgressDaySummariesProperty(): Collection
    {
        if ($this->cachedTreatmentProgressDaySummaries !== null) {
            return $this->cachedTreatmentProgressDaySummaries;
        }

        $this->cachedTreatmentProgressDaySummaries = app(PatientOverviewReadModelService::class)
            ->treatmentProgressDaySummaries($this->record);

        return $this->cachedTreatmentProgressDaySummaries;
    }

    public function getTreatmentProgressPanelProperty(): array
    {
        if ($this->cachedTreatmentProgressPanel !== null) {
            return $this->cachedTreatmentProgressPanel;
        }

        $this->cachedTreatmentProgressPanel = app(PatientOverviewReadModelService::class)->treatmentProgressPanelPayload(
            $this->record,
            $this->currentUser(),
            $this->workspaceReturnUrl,
            $this->treatmentProgress,
            $this->treatmentProgressDaySummaries,
        );

        return $this->cachedTreatmentProgressPanel;
    }

    public function getMaterialUsagesProperty(): Collection
    {
        if ($this->cachedMaterialUsages !== null) {
            return $this->cachedMaterialUsages;
        }

        $this->cachedMaterialUsages = app(PatientOverviewReadModelService::class)->materialUsages(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedMaterialUsages;
    }

    public function getFactoryOrdersProperty(): Collection
    {
        if ($this->cachedFactoryOrders !== null) {
            return $this->cachedFactoryOrders;
        }

        $this->cachedFactoryOrders = app(PatientOverviewReadModelService::class)->factoryOrders(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedFactoryOrders;
    }

    public function getMaterialIssueNotesProperty(): Collection
    {
        if ($this->cachedMaterialIssueNotes !== null) {
            return $this->cachedMaterialIssueNotes;
        }

        $this->cachedMaterialIssueNotes = app(PatientOverviewReadModelService::class)->materialIssueNotes(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedMaterialIssueNotes;
    }

    public function getLabMaterialsPanelProperty(): array
    {
        if ($this->cachedLabMaterialsPanel !== null) {
            return $this->cachedLabMaterialsPanel;
        }

        $this->cachedLabMaterialsPanel = app(PatientOverviewReadModelService::class)->labMaterialsPanelPayload(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedLabMaterialsPanel;
    }

    public function getLabMaterialSectionsProperty(): array
    {
        if ($this->cachedLabMaterialSections !== null) {
            return $this->cachedLabMaterialSections;
        }

        $this->cachedLabMaterialSections = $this->labMaterialsPanel['sections_by_key'] ?? [];

        return $this->cachedLabMaterialSections;
    }

    public function getPaymentPanelProperty(): array
    {
        if ($this->cachedPaymentPanel !== null) {
            return $this->cachedPaymentPanel;
        }

        $this->cachedPaymentPanel = app(PatientOverviewReadModelService::class)->paymentPanelPayload(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedPaymentPanel;
    }

    public function getFormsPanelProperty(): array
    {
        if ($this->cachedFormsPanel !== null) {
            return $this->cachedFormsPanel;
        }

        $this->cachedFormsPanel = app(PatientOverviewReadModelService::class)->formsPanelPayload(
            $this->record,
            $this->currentUser(),
        );

        return $this->cachedFormsPanel;
    }

    public function getRenderedFormSectionsProperty(): array
    {
        if ($this->cachedRenderedFormSections !== null) {
            return $this->cachedRenderedFormSections;
        }

        $this->cachedRenderedFormSections = array_values($this->formsPanel['sections'] ?? []);

        return $this->cachedRenderedFormSections;
    }

    public function getRenderedPaymentBlocksProperty(): array
    {
        if ($this->cachedRenderedPaymentBlocks !== null) {
            return $this->cachedRenderedPaymentBlocks;
        }

        $this->cachedRenderedPaymentBlocks = collect($this->paymentPanel['sections'] ?? [])
            ->map(function (array $section): ?array {
                $relationManager = match ($section['key'] ?? null) {
                    'invoices' => InvoicesRelationManager::class,
                    'payments' => PatientPaymentsRelationManager::class,
                    default => null,
                };

                if ($relationManager === null) {
                    return null;
                }

                return [
                    'key' => $section['key'],
                    'title' => $section['title'],
                    'relation_manager' => $relationManager,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $this->cachedRenderedPaymentBlocks;
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->visibleWorkspaceTabIds(), true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($tab);
        $this->cachedRenderedTabs = null;
    }

    protected function visibleWorkspaceTabIds(): array
    {
        return collect($this->getTabsProperty())
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && in_array($id, $this->workspaceTabs, true))
            ->values()
            ->all();
    }

    protected function buildWorkspaceReturnUrl(string $tab): string
    {
        return PatientResource::getUrl('view', [
            'record' => $this->record,
            'tab' => $tab,
        ]);
    }

    protected function getHeaderActions(): array
    {
        $headerActions = app(PatientOverviewReadModelService::class)->workspaceHeaderActions(
            $this->record,
            $this->currentUser(),
            $this->workspaceReturnUrl,
        );

        return [
            $this->workspaceHeaderAction('createTreatmentPlan', $headerActions['create_treatment_plan']),
            $this->workspaceHeaderAction('createInvoice', $headerActions['create_invoice']),
            $this->workspaceHeaderAction('createAppointment', $headerActions['create_appointment']),
            $this->workspaceHeaderAction('medicalRecord', $headerActions['medical_record']),

            Actions\EditAction::make()
                ->label('Chỉnh sửa')
                ->icon('heroicon-o-pencil'),

            Actions\DeleteAction::make()
                ->label('Xóa')
                ->icon('heroicon-o-trash'),
        ];
    }

    /**
     * @param  array{
     *     label:string,
     *     url:?string,
     *     visible:bool,
     *     icon:string,
     *     color:string,
     *     open_in_new_tab:bool
     * }  $config
     */
    protected function workspaceHeaderAction(string $name, array $config): Action
    {
        $action = Action::make($name)
            ->label($config['label'])
            ->icon($config['icon'])
            ->color($config['color'])
            ->visible(fn (): bool => $config['visible'])
            ->url(fn (): ?string => $config['url']);

        if ($config['open_in_new_tab']) {
            $action->openUrlInNewTab();
        }

        return $action;
    }

    protected function currentUser(): ?User
    {
        if ($this->cachedAuthUser !== null) {
            return $this->cachedAuthUser;
        }

        $authUser = auth()->user();

        $this->cachedAuthUser = $authUser instanceof User ? $authUser : null;

        return $this->cachedAuthUser;
    }
}
