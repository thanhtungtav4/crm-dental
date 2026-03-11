<?php

use App\Filament\Resources\PlanItems\Pages\EditPlanItem;
use App\Filament\Resources\TreatmentPlans\Pages\EditTreatmentPlan;
use App\Filament\Resources\TreatmentSessions\Pages\EditTreatmentSession;
use Illuminate\Support\Facades\File;

it('keeps patient context when opening plan item edit from treatment plan list', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $blade = File::get($bladePath);

    expect($blade)
        ->toContain("'return_url' => \$returnUrl")
        ->toContain("'patient_id' => \$patientId");
});

it('captures return url once at mount to avoid livewire internal endpoint redirect', function (): void {
    $componentPath = app_path('Livewire/PatientTreatmentPlanSection.php');
    $component = File::get($componentPath);

    expect($component)->toContain("public string \$returnUrl = '';")
        ->and($component)->toContain('$this->returnUrl = request()->fullUrl();');
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
        ->and($form)->toContain('scopeTreatmentPlanQueryByContext')
        ->and($form)->toContain("request()->integer('patient_id')");
});

it('redirects plan item edit page back to return url after save', function (): void {
    $pagePath = app_path('Filament/Resources/PlanItems/Pages/EditPlanItem.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain("Action::make('open_patient_exam_treatment')")
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
    $pageClassPath = app_path('Filament/Resources/Patients/Pages/ViewPatient.php');
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');

    $pageClass = File::get($pageClassPath);
    $blade = File::get($bladePath);

    expect($pageClass)
        ->toContain("'edit_url' => \$sessionId")
        ->toContain("route('filament.admin.resources.treatment-sessions.edit'")
        ->toContain("'return_url' => \$this->workspaceReturnUrl");

    expect($blade)
        ->toContain("{{ \$session['edit_url'] }}")
        ->toContain("@if(\$session['edit_url'])")
        ->toContain('Chỉnh sửa phiên điều trị');
});

it('captures exam-treatment workspace url once and reuses it for create and edit session links', function (): void {
    $pageClassPath = app_path('Filament/Resources/Patients/Pages/ViewPatient.php');
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');

    $pageClass = File::get($pageClassPath);
    $blade = File::get($bladePath);

    expect($pageClass)
        ->toContain("public string \$workspaceReturnUrl = '';")
        ->toContain('$this->workspaceReturnUrl = $this->buildWorkspaceReturnUrl($this->activeTab);')
        ->toContain("'return_url' => \$this->workspaceReturnUrl");

    expect($blade)
        ->toContain("route('filament.admin.resources.treatment-sessions.create', [")
        ->toContain("'return_url' => \$this->workspaceReturnUrl");
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
    $blade = File::get($bladePath);

    expect($blade)
        ->toContain('<th>Kế hoạch</th>')
        ->toContain("route('filament.admin.resources.treatment-plans.edit'")
        ->toContain('title="Sửa hạng mục"');
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
