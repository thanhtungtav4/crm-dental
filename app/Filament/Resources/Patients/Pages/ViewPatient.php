<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
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

    protected ?Collection $cachedTreatmentProgress = null;

    protected ?Collection $cachedTreatmentProgressDaySummaries = null;

    protected ?array $cachedTreatmentProgressPanel = null;

    protected ?Collection $cachedMaterialUsages = null;

    protected ?array $cachedLabMaterialsPanel = null;

    protected ?Collection $cachedFactoryOrders = null;

    protected ?Collection $cachedMaterialIssueNotes = null;

    protected ?array $cachedPaymentPanel = null;

    protected ?array $cachedFormsPanel = null;

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

    public function getTreatmentProgressProperty(): Collection
    {
        if ($this->cachedTreatmentProgress !== null) {
            return $this->cachedTreatmentProgress;
        }

        $this->cachedTreatmentProgress = app(PatientOverviewReadModelService::class)
            ->treatmentProgress($this->record, $this->workspaceReturnUrl);

        return $this->cachedTreatmentProgress;
    }

    public function getTreatmentProgressCountProperty(): int
    {
        return (int) ($this->treatmentProgressPanel['sessions_count'] ?? 0);
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

    public function getTreatmentProgressDayCountProperty(): int
    {
        return (int) ($this->treatmentProgressPanel['days_count'] ?? 0);
    }

    public function getTreatmentProgressTotalAmountProperty(): float
    {
        return (float) ($this->treatmentProgressPanel['total_amount'] ?? 0);
    }

    public function getTreatmentProgressTotalAmountFormattedProperty(): string
    {
        return (string) ($this->treatmentProgressPanel['total_amount_formatted'] ?? '0');
    }

    public function getTreatmentProgressSummaryProperty(): array
    {
        return [
            'sessions_count' => (int) ($this->treatmentProgressPanel['sessions_count'] ?? 0),
            'days_count' => (int) ($this->treatmentProgressPanel['days_count'] ?? 0),
            'total_amount' => (float) ($this->treatmentProgressPanel['total_amount'] ?? 0),
            'total_amount_formatted' => (string) ($this->treatmentProgressPanel['total_amount_formatted'] ?? '0'),
        ];
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

    public function getPaymentSummaryProperty(): array
    {
        return (array) ($this->paymentPanel['summary'] ?? []);
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

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->visibleWorkspaceTabIds(), true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($tab);
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
            Action::make('createTreatmentPlan')
                ->label((string) ($headerActions['create_treatment_plan']['label'] ?? 'Tạo kế hoạch điều trị'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success')
                ->visible(fn (): bool => (bool) ($headerActions['create_treatment_plan']['visible'] ?? false))
                ->url(fn (): string => (string) ($headerActions['create_treatment_plan']['url'] ?? ''))
                ->openUrlInNewTab(),

            Action::make('createInvoice')
                ->label((string) ($headerActions['create_invoice']['label'] ?? 'Tạo hóa đơn'))
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->visible(fn (): bool => (bool) ($headerActions['create_invoice']['visible'] ?? false))
                ->url(fn (): string => (string) ($headerActions['create_invoice']['url'] ?? ''))
                ->openUrlInNewTab(),

            Action::make('createAppointment')
                ->label((string) ($headerActions['create_appointment']['label'] ?? 'Đặt lịch hẹn'))
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->visible(fn (): bool => (bool) ($headerActions['create_appointment']['visible'] ?? false))
                ->url(fn (): string => (string) ($headerActions['create_appointment']['url'] ?? ''))
                ->openUrlInNewTab(),

            Action::make('medicalRecord')
                ->label(fn (): string => (string) ($headerActions['medical_record']['label'] ?? 'Mở bệnh án điện tử'))
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->url(fn (): ?string => $headerActions['medical_record']['url'] ?? null)
                ->visible(fn (): bool => (bool) ($headerActions['medical_record']['visible'] ?? false)),

            Actions\EditAction::make()
                ->label('Chỉnh sửa')
                ->icon('heroicon-o-pencil'),

            Actions\DeleteAction::make()
                ->label('Xóa')
                ->icon('heroicon-o-trash'),
        ];
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
