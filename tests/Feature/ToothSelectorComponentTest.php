<?php

use Illuminate\Support\Facades\File;

it('uses dark-mode friendly states in the tooth selector component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-selector.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothSelector.php');
    $viewConfigPath = app_path('Support/ToothSelectionViewConfig.php');
    $relationManagerPath = app_path('Filament/Resources/Patients/RelationManagers/DiagnosisAndTreatmentRelationManager.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);
    $relationManager = File::get($relationManagerPath);

    expect($blade)->toContain('class="tooth-selector text-gray-700 dark:text-gray-200"')
        ->and($blade)->toContain('@foreach($selectorRows as $row)')
        ->and($blade)->toContain('@foreach($legendItems as $legendItem)')
        ->and($blade)->toContain('$selectedButtonClasses')
        ->and($blade)->toContain('dark:border-gray-600')
        ->and($blade)->toContain('dark:text-gray-300');

    expect($component)->toContain('class ToothSelector extends Field')
        ->and($component)->toContain('use App\\Support\\ToothSelectionViewConfig;')
        ->and($component)->toContain("protected string \$view = 'filament.forms.components.tooth-selector';")
        ->and($component)->toContain('$viewConfig = app(ToothSelectionViewConfig::class);')
        ->and($component)->toContain("'selectorRows' => \$viewConfig->selectorRows()")
        ->and($component)->toContain("'legendItems' => \$viewConfig->selectorLegendItems()")
        ->and($component)->toContain("'selectedButtonClasses' => \$viewConfig->selectedSelectorButtonClasses()")
        ->and($component)->toContain("'defaultButtonClasses' => \$viewConfig->defaultSelectorButtonClasses()")
        ->and($component)->toContain("'emptySelectionLabel' => \$viewConfig->emptySelectionLabel()");
    expect($viewConfig)->toContain('function selectorRows')
        ->and($viewConfig)->toContain('function selectorLegendItems')
        ->and($viewConfig)->toContain('function selectedSelectorButtonClasses')
        ->and($viewConfig)->toContain('dark:bg-primary-500/15 dark:text-primary-100')
        ->and($viewConfig)->toContain('dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300 dark:hover:border-gray-500')
        ->and($viewConfig)->toContain("return 'Chưa chọn';");

    expect($relationManager)->toContain('use App\\Filament\\Forms\\Components\\ToothSelector;')
        ->and($relationManager)->toContain("ToothSelector::make('tooth_ids')")
        ->and($relationManager)->toContain('protected function diagnosisOptionsForSelectedTeeth(Get $get): array')
        ->and($relationManager)->toContain('protected function mutatePlanItemFormData(array $data): array')
        ->and($relationManager)->toContain('protected function syncServiceDefaults(mixed $state, Set $set): void')
        ->and($relationManager)->toContain('protected function draftTreatmentPlan(): TreatmentPlan')
        ->and($relationManager)->toContain('protected function toothChartModalData(): array')
        ->and($relationManager)->toContain('protected function selectTeethStep(): Step')
        ->and($relationManager)->toContain('protected function selectDiagnosisStep(): Step')
        ->and($relationManager)->toContain('protected function selectServiceStep(): Step')
        ->and($relationManager)->toContain('$this->selectTeethStep(),')
        ->and($relationManager)->toContain('$this->selectDiagnosisStep(),')
        ->and($relationManager)->toContain('$this->selectServiceStep(),')
        ->and($relationManager)->toContain('->options(fn (Get $get): array => $this->diagnosisOptionsForSelectedTeeth($get))')
        ->and($relationManager)->toContain('->afterStateUpdated(fn ($state, Set $set) => $this->syncServiceDefaults($state, $set))')
        ->and($relationManager)->toContain('->mutateFormDataUsing(fn (array $data): array => $this->mutatePlanItemFormData($data))')
        ->and($relationManager)->not->toContain("ViewField::make('tooth_ids')")
        ->and($relationManager)->not->toContain("->view('filament.forms.components.tooth-selector')")
        ->and($relationManager)->not->toContain('\\App\\Models\\Service::find($state)');
});
