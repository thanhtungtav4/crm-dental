<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;
use App\Services\PhiAccessAuditService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\Computed;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public string $activeTab = 'basic-info';

    public string $workspaceReturnUrl = '';

    protected ?User $cachedAuthUser = null;

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

    #[Computed]
    public function workspaceViewState(): array
    {
        return app(PatientOverviewReadModelService::class)
            ->workspaceViewState(
                $this->record,
                $this->currentUser(),
                $this->activeTab,
                $this->workspaceReturnUrl,
            );
    }

    /**
     * @return array{partial:string,data:array<string,mixed>}
     */
    #[Computed]
    public function activeWorkspaceTabView(): array
    {
        $workspace = $this->workspaceViewState();

        return match ($this->activeTab) {
            'basic-info' => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.basic-info-tab',
                'data' => [
                    'record' => $this->record,
                    'basicInfoPanels' => $workspace['basic_info_panels'] ?? [],
                ],
            ],
            'exam-treatment' => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.exam-treatment-tab',
                'data' => [
                    'record' => $this->record,
                    'renderedTreatmentProgressPanel' => $workspace['rendered_treatment_progress_panel'] ?? [],
                ],
            ],
            'prescriptions' => [
                'partial' => 'filament.resources.patients.pages.partials.livewire-tab-panel',
                'data' => [
                    'component' => \App\Filament\Resources\Patients\RelationManagers\PrescriptionsRelationManager::class,
                    'parameters' => [
                        'ownerRecord' => $this->record,
                        'pageClass' => static::class,
                    ],
                    'wireKey' => 'patient-'.$this->record->id.'-prescriptions',
                    'wrapperClass' => 'crm-rel-tab crm-rel-tab-prescriptions',
                ],
            ],
            'photos' => [
                'partial' => 'filament.resources.patients.pages.partials.livewire-tab-panel',
                'data' => [
                    'component' => \App\Filament\Resources\Patients\RelationManagers\PatientPhotosRelationManager::class,
                    'parameters' => [
                        'ownerRecord' => $this->record,
                        'pageClass' => static::class,
                    ],
                    'wireKey' => 'patient-'.$this->record->id.'-photos',
                    'wrapperClass' => 'crm-rel-tab crm-rel-tab-photos',
                ],
            ],
            'lab-materials' => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.lab-materials-tab',
                'data' => [
                    'record' => $this->record,
                    'renderedLabMaterialSections' => $workspace['rendered_lab_material_sections'] ?? [],
                ],
            ],
            'appointments' => [
                'partial' => 'filament.resources.patients.pages.partials.livewire-tab-panel',
                'data' => [
                    'component' => \App\Filament\Resources\Patients\RelationManagers\AppointmentsRelationManager::class,
                    'parameters' => [
                        'ownerRecord' => $this->record,
                        'pageClass' => static::class,
                    ],
                    'wireKey' => 'patient-'.$this->record->id.'-appointments',
                    'wrapperClass' => 'crm-rel-tab crm-rel-tab-appointments',
                ],
            ],
            'payments' => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.payments-tab',
                'data' => [
                    'record' => $this->record,
                    'renderedPaymentPanel' => $workspace['rendered_payment_panel'] ?? [],
                ],
            ],
            'forms' => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.forms-tab',
                'data' => [
                    'record' => $this->record,
                    'renderedFormsPanel' => $workspace['rendered_forms_panel'] ?? [],
                ],
            ],
            'care' => [
                'partial' => 'filament.resources.patients.pages.partials.livewire-tab-panel',
                'data' => [
                    'component' => \App\Filament\Resources\Patients\Relations\PatientNotesRelationManager::class,
                    'parameters' => [
                        'ownerRecord' => $this->record,
                        'pageClass' => static::class,
                    ],
                    'wireKey' => 'patient-'.$this->record->id.'-care',
                    'wrapperClass' => 'crm-care-tab',
                    'innerWrapperClass' => 'crm-care-manager',
                ],
            ],
            'activity-log' => [
                'partial' => 'filament.resources.patients.pages.partials.livewire-tab-panel',
                'data' => [
                    'component' => \App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget::class,
                    'parameters' => [
                        'record' => $this->record,
                    ],
                    'wireKey' => 'patient-'.$this->record->id.'-activity-log',
                ],
            ],
            default => [
                'partial' => 'filament.resources.patients.pages.partials.tabs.basic-info-tab',
                'data' => [
                    'record' => $this->record,
                    'basicInfoPanels' => $workspace['basic_info_panels'] ?? [],
                ],
            ],
        };
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, $this->visibleWorkspaceTabIds(), true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($tab);
        unset($this->workspaceViewState);
        unset($this->activeWorkspaceTabView);
    }

    protected function visibleWorkspaceTabIds(): array
    {
        return collect($this->workspaceViewState()['tabs'] ?? [])
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
        $headerActions = $this->workspaceViewState()['header_actions'] ?? ['items' => []];

        return [
            ...collect($headerActions['items'])
                ->map(fn (array $config): Action => $this->workspaceHeaderAction($config['name'], $config))
                ->all(),

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
