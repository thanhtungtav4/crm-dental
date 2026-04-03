<?php

use Illuminate\Support\Facades\File;

it('renders shared patient workspace action partials from the page view', function (): void {
    $view = File::get(resource_path('views/filament/resources/patients/pages/view-patient.blade.php'));
    $actionPromptCardPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/action-prompt-card.blade.php'));
    $copyButtonPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/copy-button.blade.php'));
    $detailActionPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/detail-action.blade.php'));
    $featureTableSectionPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/feature-table-section.blade.php'));
    $infoCardPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/info-card.blade.php'));
    $linkListSectionPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/link-list-section.blade.php'));
    $livewireTabPanelPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/livewire-tab-panel.blade.php'));
    $paymentSummaryPanelPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/payment-summary-panel.blade.php'));
    $patientOverviewCardPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/patient-overview-card.blade.php'));
    $relationManagerSectionPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/relation-manager-section.blade.php'));
    $relationManagerBlockPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/relation-manager-block.blade.php'));
    $sectionHeaderPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/section-header.blade.php'));
    $basicInfoTabPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/tabs/basic-info-tab.blade.php'));
    $examTreatmentTabPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/tabs/exam-treatment-tab.blade.php'));
    $formsTabPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/tabs/forms-tab.blade.php'));
    $labMaterialsTabPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/tabs/lab-materials-tab.blade.php'));
    $paymentsTabPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/tabs/payments-tab.blade.php'));
    $treatmentProgressPanelPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/treatment-progress-panel.blade.php'));
    $workspaceTabsNavPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/workspace-tabs-nav.blade.php'));
    $workspaceShellStatePartial = File::get(resource_path('views/filament/resources/patients/pages/partials/workspace-shell-state.blade.php'));
    $workspaceShellInitPartial = File::get(resource_path('views/filament/resources/patients/pages/partials/workspace-shell-init.blade.php'));

    expect($view)
        ->toContain("@include('filament.resources.patients.pages.partials.patient-overview-card'")
        ->toContain("@include('filament.resources.patients.pages.partials.workspace-tabs-nav'")
        ->toContain("@include(\$this->activeWorkspaceTabView['partial'], \$this->activeWorkspaceTabView['data'])")
        ->toContain("'overviewCard' => \$this->workspaceViewState['overview_card'] ?? []")
        ->toContain("'tabs' => \$this->workspaceViewState['rendered_tabs'] ?? []")
        ->toContain("id=\"{{ \$this->workspaceViewState['active_panel_id'] ?? '' }}\"")
        ->toContain("aria-labelledby=\"{{ \$this->workspaceViewState['active_tab_button_id'] ?? '' }}\"")
        ->toContain("x-data=\"@include('filament.resources.patients.pages.partials.workspace-shell-state')\"")
        ->toContain("x-init=\"@include('filament.resources.patients.pages.partials.workspace-shell-init')\"")
        ->not->toContain("@elseif(\$activeTab === 'payments')")
        ->not->toContain("@elseif(\$activeTab === 'forms')")
        ->not->toContain("@elseif(\$activeTab === 'lab-materials')")
        ->not->toContain('@php');

    expect($workspaceShellStatePartial)
        ->toContain("activeTab: \$wire.entangle('activeTab')")
        ->toContain('ensureActiveTabVisible()')
        ->toContain('copyToClipboard(value, label)')
        ->toContain('window.navigator?.clipboard?.writeText')
        ->toContain('`${label} đã được sao chép`');

    expect($workspaceShellInitPartial)
        ->toContain("payments: 'payment'")
        ->toContain("appointments: 'appointment'")
        ->toContain('syncTabQuery(activeTab);')
        ->toContain("url.searchParams.set('tab', tabQueryMap[val] ?? val);")
        ->toContain("\$watch('activeTab', (val) => {");

    expect($copyButtonPartial)
        ->toContain('copyToClipboard(@js($copyValue), @js($copyLabel))')
        ->toContain('heroicon-o-square-2-stack');

    expect($actionPromptCardPartial)
        ->toContain("\$prompt['title']")
        ->toContain("\$prompt['description']")
        ->toContain("\$prompt['action']['tab']")
        ->toContain("\$prompt['action']['button_class']")
        ->toContain("\$prompt['action']['label']");

    expect($detailActionPartial)
        ->toContain('@if($url)')
        ->toContain('<a href="{{ $url }}" class="{{ $actionClass }}">')
        ->toContain('<span class="{{ $actionClass }}">{{ $label }}</span>');

    expect($featureTableSectionPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.section-header'")
        ->toContain("@include('filament.resources.patients.pages.partials.detail-action'")
        ->toContain("@foreach(\$section['table']['columns'] as \$column)")
        ->toContain("@forelse(\$section['table']['rows'] as \$row)")
        ->toContain("\$section['table']['empty_text']");

    expect($linkListSectionPartial)
        ->toContain("@forelse(\$section['links'] as \$link)")
        ->toContain('crm-link-list-empty');

    expect($livewireTabPanelPartial)
        ->toContain('@livewire($component, $parameters, key($wireKey))')
        ->toContain('@if($innerWrapperClass)')
        ->toContain('@class([$wrapperClass])');

    expect($paymentSummaryPanelPartial)
        ->toContain("\$panel['title']")
        ->toContain("@foreach(\$panel['actions'] as \$action)")
        ->toContain("@foreach(\$panel['metrics'] as \$metric)");

    expect($patientOverviewCardPartial)
        ->toContain("\$overviewCard['identity_header']")
        ->toContain("\$overviewCard['basic_info_grid']")
        ->toContain("\$identityHeader['avatar_initials']")
        ->toContain("@include('filament.resources.patients.pages.partials.copy-button'")
        ->toContain("@foreach(\$basicInfoGrid['cards'] as \$card)")
        ->toContain("\$basicInfoGrid['address_card']['value']");

    expect($relationManagerSectionPartial)
        ->toContain("\$section['title']")
        ->toContain("\$section['description']")
        ->toContain("@include('filament.resources.patients.pages.partials.section-header'")
        ->toContain('@livewire($relationManager')
        ->toContain('key($wireKey)');

    expect($relationManagerBlockPartial)
        ->toContain("@livewire(\$block['relation_manager']")
        ->toContain('key($wireKey)');

    expect($basicInfoTabPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.livewire-tab-panel'")
        ->toContain("@include('filament.resources.patients.pages.partials.relation-manager-section'")
        ->toContain("@include('filament.resources.patients.pages.partials.action-prompt-card'")
        ->toContain("\$basicInfoPanels['empty_state_text']");

    expect($examTreatmentTabPartial)
        ->toContain("@livewire('patient-exam-form'")
        ->toContain("@livewire('patient-treatment-plan-section'")
        ->toContain("@include('filament.resources.patients.pages.partials.treatment-progress-panel'");

    expect($labMaterialsTabPartial)
        ->toContain('@foreach($renderedLabMaterialSections as $section)')
        ->toContain("@include('filament.resources.patients.pages.partials.feature-table-section'");

    expect($paymentsTabPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.payment-summary-panel'")
        ->toContain("@foreach(\$renderedPaymentPanel['blocks'] ?? [] as \$block)")
        ->toContain("@include('filament.resources.patients.pages.partials.relation-manager-block'");

    expect($formsTabPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.section-header'")
        ->toContain("@foreach(\$renderedFormsPanel['sections'] ?? [] as \$section)")
        ->toContain("@include('filament.resources.patients.pages.partials.link-list-section'");

    expect($treatmentProgressPanelPartial)
        ->toContain("\$panel['section_title']")
        ->toContain("@foreach(\$panel['day_summaries'] as \$summary)")
        ->toContain("@forelse(\$panel['rows'] as \$session)")
        ->toContain("\$panel['empty_text']");

    expect($workspaceTabsNavPartial)
        ->toContain('patient-workspace-tab-select')
        ->toContain('wire:change="setActiveTab($event.target.value)"')
        ->toContain('aria-label="Chọn khu vực làm việc hồ sơ bệnh nhân"')
        ->toContain('@foreach($tabs as $tab)')
        ->toContain("wire:click=\"setActiveTab('{{ \$tab['id'] }}')\"")
        ->toContain("\$tab['button_id']")
        ->toContain("\$tab['count'] !== null");

    expect($infoCardPartial)
        ->toContain("@include('filament.resources.patients.pages.partials.copy-button'")
        ->toContain("x-filament::icon :icon=\"\$card['icon']\"")
        ->toContain("@elseif(\$card['href'])")
        ->toContain('crm-patient-info-age');

    expect($sectionHeaderPartial)
        ->toContain('<h3 class="crm-feature-card-title">{{ $title }}</h3>')
        ->toContain('<p class="crm-feature-card-description">{{ $description }}</p>')
        ->toContain('@if($action !== null)')
        ->toContain("class=\"crm-btn {{ \$action['button_class'] }} crm-btn-md\"");
});
