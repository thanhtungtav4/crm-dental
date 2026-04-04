<?php

use App\Filament\Resources\PlanItems\Pages\EditPlanItem;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Filament\Resources\TreatmentSessions\Pages\EditTreatmentSession;
use App\Services\PlanItemWorkflowService;
use Illuminate\Support\Facades\File;

it('keeps patient context when opening plan item edit from treatment plan list', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $listSectionPath = resource_path('views/livewire/partials/patient-treatment-plan/list-section.blade.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $listSection = File::get($listSectionPath);

    expect($blade)
        ->toContain("@include('livewire.partials.patient-treatment-plan.list-section'")
        ->and($listSection)
        ->toContain("{{ \$row['edit_url'] }}")
        ->and($component)
        ->toContain("route('filament.admin.resources.plan-items.edit'")
        ->toContain("'return_url' => \$this->returnUrl")
        ->toContain("'patient_id' => \$this->patientId");
});

it('captures return url once at mount to avoid livewire internal endpoint redirect', function (): void {
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $component = File::get($componentPath);

    expect($component)->toContain("public string \$returnUrl = '';")
        ->and($component)->toContain('$this->returnUrl = request()->fullUrl();');
});

it('renders treatment plan section rows from prepared view state payloads', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $listSectionPath = resource_path('views/livewire/partials/patient-treatment-plan/list-section.blade.php');
    $planModalPath = resource_path('views/livewire/partials/patient-treatment-plan/plan-modal.blade.php');
    $procedureModalPath = resource_path('views/livewire/partials/patient-treatment-plan/procedure-modal.blade.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $listSection = File::get($listSectionPath);
    $planModal = File::get($planModalPath);
    $procedureModal = File::get($procedureModalPath);

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('livewire.partials.patient-treatment-plan.list-section'")
        ->toContain("@include('livewire.partials.patient-treatment-plan.plan-modal'")
        ->toContain("@include('livewire.partials.patient-treatment-plan.procedure-modal'")
        ->and($listSection)
        ->toContain("{{ \$viewState['plan_count'] }} hồ sơ")
        ->toContain("@forelse(\$viewState['plan_rows'] as \$row)")
        ->toContain("href=\"{{ \$row['plan_url'] }}\"")
        ->toContain("{{ \$row['edit_url'] }}")
        ->toContain("@foreach(\$viewState['summary_panels'] as \$panel)")
        ->and($planModal)
        ->toContain('@if($isVisible)')
        ->toContain("@forelse(\$viewState['draft_rows'] as \$row)")
        ->toContain("wire:model=\"draftItems.{{ \$row['index'] }}.diagnosis_ids\"")
        ->and($procedureModal)
        ->toContain('@if($isVisible)')
        ->toContain('wire:click="selectCategory(null)"')
        ->toContain("@forelse(\$viewState['service_rows'] as \$service)")
        ->and($component)
        ->toContain("'viewState' => \$this->buildViewState(\$sectionData)")
        ->toContain('protected function buildViewState(array $sectionData): array')
        ->toContain('protected function planRows(Collection $planItems, Collection $diagnosisMap): array')
        ->toContain('protected function draftRows(array $diagnosisDetails): array')
        ->toContain('protected function serviceRows(Collection $services): array');
});

it('exposes detailed fields in plan item edit form for treatment workflow', function (): void {
    $formPath = app_path('Filament/Resources/PlanItems/Schemas/PlanItemForm.php');
    $form = File::get($formPath);

    expect($form)->toContain("Select::make('diagnosis_ids')")
        ->and($form)->toContain("Select::make('approval_status')")
        ->and($form)->toContain("TextInput::make('discount_percent')")
        ->and($form)->toContain("TextInput::make('discount_amount')")
        ->and($form)->toContain("TextInput::make('vat_amount')")
        ->and($form)->toContain("TextInput::make('final_amount')")
        ->and($form)->toContain("Select::make('status')")
        ->and($form)->toContain('Bắt đầu / Hoàn thành / Hủy')
        ->and($form)->toContain('scopeAccessibleTreatmentPlanQuery')
        ->and($form)->toContain('scopeAccessibleTreatmentPlans')
        ->and($form)->toContain('scopeTreatmentPlanQueryByContext')
        ->and($form)->toContain("request()->integer('patient_id')");
});

it('redirects plan item edit page back to return url after save', function (): void {
    $pagePath = app_path('Filament/Resources/PlanItems/Pages/EditPlanItem.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain("Action::make('open_patient_exam_treatment')")
        ->and($page)->toContain("Action::make('start_treatment')")
        ->and($page)->toContain("Action::make('complete_visit')")
        ->and($page)->toContain("Action::make('complete_treatment')")
        ->and($page)->toContain("Action::make('cancel_treatment')")
        ->and($page)->toContain(PlanItemWorkflowService::class)
        ->and($page)->toContain('DeleteAction::make()')
        ->and($page)->toContain('TreatmentDeletionGuardService')
        ->and($page)->not->toContain('RestoreAction::make()')
        ->and($page)->not->toContain('ForceDeleteAction::make()')
        ->and($page)->toContain('->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl(\'index\'))')
        ->and($page)->toContain("request()->integer('patient_id')")
        ->and($page)->toContain('assertRecordMatchesPatientContext')
        ->and($page)->toContain("'tab' => 'exam-treatment'")
        ->and($page)->toContain("request()->query('return_url')")
        ->and($page)->toContain('isDisallowedReturnPath')
        ->and($page)->toContain('isGetAccessiblePath');
});

it('rejects livewire internal endpoint as return url', function (): void {
    $page = app(EditPlanItem::class);
    $sanitizeReturnUrl = function (mixed $returnUrl): ?string {
        return $this->sanitizeReturnUrl($returnUrl);
    };
    $sanitizeReturnUrl = $sanitizeReturnUrl->bindTo($page, EditPlanItem::class);

    expect($sanitizeReturnUrl(url('/livewire/update')))->toBeNull()
        ->and($sanitizeReturnUrl(url('/admin')))->toBe(url('/admin'))
        ->and($sanitizeReturnUrl('https://example.org/admin'))->toBeNull();
});

it('preserves patient tab context when opening treatment plan edit from patient relation manager', function (): void {
    $relationManagerPath = app_path('Filament/Resources/Patients/RelationManagers/TreatmentPlansRelationManager.php');
    $relationManager = File::get($relationManagerPath);

    expect($relationManager)
        ->toContain("'return_url' => request()->fullUrl()");
});

it('redirects treatment plan edit page back to safe return url after save and destructive actions', function (): void {
    $pagePath = app_path('Filament/Resources/TreatmentPlans/Pages/EditTreatmentPlan.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain('DeleteAction::make()')
        ->and($page)->toContain('TreatmentDeletionGuardService')
        ->and($page)->not->toContain('ForceDeleteAction::make()')
        ->and($page)->not->toContain('RestoreAction::make()')
        ->and($page)->toContain('->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl(\'index\'))')
        ->and($page)->toContain("request()->query('return_url')")
        ->and($page)->toContain('isDisallowedReturnPath')
        ->and($page)->toContain('isGetAccessiblePath');
});

it('rejects livewire internal endpoint as return url for treatment plan edit page', function (): void {
    $page = app(EditTreatmentPlan::class);
    $sanitizeReturnUrl = function (mixed $returnUrl): ?string {
        return $this->sanitizeReturnUrl($returnUrl);
    };
    $sanitizeReturnUrl = $sanitizeReturnUrl->bindTo($page, EditTreatmentPlan::class);

    expect($sanitizeReturnUrl(url('/livewire/update')))->toBeNull()
        ->and($sanitizeReturnUrl(url('/admin/treatment-plans')))->toBe(url('/admin/treatment-plans'))
        ->and($sanitizeReturnUrl('https://example.org/admin'))->toBeNull();
});

it('redirects treatment session edit page back to safe return url after save and delete', function (): void {
    $pagePath = app_path('Filament/Resources/TreatmentSessions/Pages/EditTreatmentSession.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain('DeleteAction::make()')
        ->and($page)->toContain('TreatmentDeletionGuardService')
        ->and($page)->toContain('->successRedirectUrl(fn (): string => $this->resolveReturnUrl() ?? static::getResource()::getUrl(\'index\'))')
        ->and($page)->toContain("request()->query('return_url')")
        ->and($page)->toContain('isDisallowedReturnPath')
        ->and($page)->toContain('isGetAccessiblePath');
});

it('rejects livewire internal endpoint as return url for treatment session edit page', function (): void {
    $page = app(EditTreatmentSession::class);
    $sanitizeReturnUrl = function (mixed $returnUrl): ?string {
        return $this->sanitizeReturnUrl($returnUrl);
    };
    $sanitizeReturnUrl = $sanitizeReturnUrl->bindTo($page, EditTreatmentSession::class);

    expect($sanitizeReturnUrl(url('/livewire/update')))->toBeNull()
        ->and($sanitizeReturnUrl(url('/admin/treatment-sessions')))->toBe(url('/admin/treatment-sessions'))
        ->and($sanitizeReturnUrl('https://example.org/admin'))->toBeNull();
});

it('provides treatment session edit link from patient exam treatment progress table', function (): void {
    $pageClassPath = app_path('Services/PatientOverviewReadModelService.php');
    $bladePath = resource_path('views/filament/resources/patients/pages/partials/treatment-progress-panel.blade.php');

    $pageClass = File::get($pageClassPath);
    $blade = File::get($bladePath);

    expect($pageClass)
        ->toContain('$editUrl = $sessionId')
        ->toContain("route('filament.admin.resources.treatment-sessions.edit'")
        ->toContain("'return_url' => \$workspaceReturnUrl");

    expect($blade)
        ->toContain("@if(\$session['edit_action'])")
        ->toContain("href=\"{{ \$session['edit_action']['url'] }}\"")
        ->toContain("title=\"{{ \$session['edit_action']['label'] }}\"");
});

it('captures exam-treatment workspace url once and reuses it for create and edit session links', function (): void {
    $pageClassPath = app_path('Filament/Resources/Patients/Pages/ViewPatient.php');
    $readModelPath = app_path('Services/PatientOverviewReadModelService.php');

    $pageClass = File::get($pageClassPath);
    $readModel = File::get($readModelPath);

    expect($pageClass)
        ->toContain("public string \$workspaceReturnUrl = '';")
        ->toContain('$this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($this->activeTab);')
        ->toContain('$this->workspaceReturnUrl,');

    expect($readModel)
        ->toContain("route('filament.admin.resources.treatment-sessions.create', [")
        ->toContain("'return_url' => \$workspaceReturnUrl");
});

it('supports safe return url redirect for treatment plan create page', function (): void {
    $pagePath = app_path('Filament/Resources/TreatmentPlans/Pages/CreateTreatmentPlan.php');
    $page = File::get($pagePath);

    expect($page)
        ->toContain('protected function getRedirectUrl(): string')
        ->toContain("request()->query('return_url')")
        ->toContain("request()->integer('patient_id')")
        ->toContain("'tab' => 'exam-treatment'")
        ->toContain('isDisallowedReturnPath')
        ->toContain('isGetAccessiblePath');
});

it('supports safe return url redirect for treatment session create page', function (): void {
    $pagePath = app_path('Filament/Resources/TreatmentSessions/Pages/CreateTreatmentSession.php');
    $page = File::get($pagePath);

    expect($page)
        ->toContain('protected function getRedirectUrl(): string')
        ->toContain("request()->query('return_url')")
        ->toContain("request()->integer('patient_id')")
        ->toContain("request()->integer('treatment_plan_id')")
        ->toContain("'tab' => 'exam-treatment'")
        ->toContain('isDisallowedReturnPath')
        ->toContain('isGetAccessiblePath');
});

it('shows treatment plan link in patient treatment list to avoid plan item flow confusion', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $listSectionPath = resource_path('views/livewire/partials/patient-treatment-plan/list-section.blade.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $listSection = File::get($listSectionPath);

    expect($blade)
        ->toContain("@include('livewire.partials.patient-treatment-plan.list-section'");

    expect($listSection)
        ->toContain('<th>Kế hoạch</th>')
        ->toContain("href=\"{{ \$row['plan_url'] }}\"")
        ->toContain('title="Sửa hạng mục"');

    expect($component)
        ->toContain("route('filament.admin.resources.treatment-plans.edit'");
});

it('adds quick action from treatment plan and session edit pages back to patient exam-treatment', function (): void {
    $planEditPath = app_path('Filament/Resources/TreatmentPlans/Pages/EditTreatmentPlan.php');
    $sessionEditPath = app_path('Filament/Resources/TreatmentSessions/Pages/EditTreatmentSession.php');

    $planEdit = File::get($planEditPath);
    $sessionEdit = File::get($sessionEditPath);

    expect($planEdit)
        ->toContain("Action::make('open_patient_exam_treatment')")
        ->toContain('resolvePatientExamTreatmentUrl')
        ->toContain("'tab' => 'exam-treatment'");

    expect($sessionEdit)
        ->toContain("Action::make('open_patient_exam_treatment')")
        ->toContain('resolvePatientExamTreatmentUrl')
        ->toContain("'tab' => 'exam-treatment'");
});
