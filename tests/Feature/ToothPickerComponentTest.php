<?php

use Illuminate\Support\Facades\File;

it('renders child dentition picker instead of placeholder message', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothPicker.php');
    $viewConfigPath = app_path('Support/ToothSelectionViewConfig.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);

    expect($blade)->not->toContain('@php');
    expect($blade)->not->toContain('Chức năng chọn răng sữa đang được cập nhật...');
    expect($blade)->toContain('@foreach($toothGroups as $tabKey => $groups)');
    expect($blade)->toContain('@foreach($groups as $group)');
    expect($blade)->toContain('@foreach($group[\'teeth\'] as $tooth)');
    expect($component)->toContain('use App\\Support\\ToothSelectionViewConfig;');
    expect($component)->toContain('$viewConfig = app(ToothSelectionViewConfig::class);');
    expect($component)->toContain("'toothGroups' => \$viewConfig->pickerToothGroups()");
    expect($viewConfig)->toContain('function pickerToothGroups');
    expect($viewConfig)->toContain("'Răng sữa hàm trên'");
    expect($viewConfig)->toContain("'Răng sữa hàm dưới'");
    expect($viewConfig)->toContain("'crm-tooth-picker-grid-10'");
    expect($viewConfig)->toContain("'grid-template-columns: repeat(10, minmax(0, 1fr));'");
});

it('renders picker tabs and selection classes from component view data', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('@foreach($tabs as $tabConfig)');
    expect($blade)->toContain('$tabConfig[\'key\']');
    expect($blade)->toContain('$selectedButtonClasses');
    expect($blade)->toContain('$defaultButtonClasses');
    expect($blade)->toContain('$selectedLabelClasses');
    expect($blade)->toContain('$defaultLabelClasses');
});

it('uses draft state and explicit cancel semantics in tooth picker modal', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('draftState: []');
    expect($blade)->toContain('cancelPicker()');
    expect($blade)->toContain('confirmPicker()');
    expect($blade)->toContain('@click.self="cancelPicker()"');
    expect($blade)->toContain('@keydown.escape.window="if (modalOpen) { cancelPicker(); }"');
    expect($blade)->toContain('@click="confirmPicker()"');
});

it('uses dark-mode friendly trigger and selection states in tooth picker modal', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothPicker.php');
    $viewConfigPath = app_path('Support/ToothSelectionViewConfig.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);

    expect($blade)->toContain('dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800')
        ->and($blade)->toContain('class="crm-modal-close-btn"')
        ->and($blade)->toContain('$selectedButtonClasses')
        ->and($blade)->toContain('dark:border-gray-700');
    expect($component)->toContain("'selectedButtonClasses' => \$viewConfig->selectedPickerButtonClasses()");
    expect($viewConfig)->toContain('function selectedPickerButtonClasses')
        ->and($viewConfig)->toContain('dark:bg-primary-500/15 dark:ring-offset-gray-900');
});

it('handles string hydration and dehydration inside the tooth picker field', function (): void {
    $componentPath = app_path('Filament/Forms/Components/ToothPicker.php');
    $treatmentPlanFormPath = app_path('Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanForm.php');
    $component = File::get($componentPath);
    $treatmentPlanForm = File::get($treatmentPlanFormPath);

    expect($component)
        ->toContain('$this->afterStateHydrated(function (ToothPicker $component, mixed $state): void {')
        ->toContain('$component->state($this->hydrateToothSelectionState($state));')
        ->toContain('$this->dehydrateStateUsing(fn (mixed $state): mixed => $this->dehydrateToothSelectionState($state));')
        ->toContain('protected function hydrateToothSelectionState(string $state): array')
        ->toContain('protected function dehydrateToothSelectionState(mixed $state): mixed');

    expect($treatmentPlanForm)
        ->toContain("ToothPicker::make('tooth_number')")
        ->not->toContain('->afterStateHydrated(function ($component, $state): void {')
        ->not->toContain("->dehydrateStateUsing(fn (\$state) => is_array(\$state) ? implode(',', \$state) : \$state)");
});
