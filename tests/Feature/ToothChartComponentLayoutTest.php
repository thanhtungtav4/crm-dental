<?php

use Illuminate\Support\Facades\File;

it('uses segmented dentition controls in tooth chart component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothChart.php');
    $viewConfigPath = app_path('Support/ToothChartViewConfig.php');
    $rowsPartialPath = resource_path('views/filament/forms/components/partials/tooth-chart-rows.blade.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);
    $rowsPartial = File::get($rowsPartialPath);

    expect($blade)->not->toContain('@php')
        ->and($blade)->toContain('class="crm-dentition-toggle" role="tablist"');
    expect($blade)->toContain('@foreach($dentitionOptions as $option)');
    expect($blade)->toContain("dentitionMode === '{{ \$option['mode'] }}' ? 'is-active' : ''");
    expect($blade)->toContain('$dentitionOptionActiveStyle');
    expect($blade)->toContain('$dentitionOptionIdleStyle');
    expect($blade)->toContain("@include('filament.forms.components.partials.tooth-chart-rows', ['rows' => \$toothRows])");
    expect($rowsPartial)->toContain("@props(['rows'])");
    expect($rowsPartial)->toContain('@foreach($rows as $row)');
    expect($component)->toContain('$viewConfig = app(ToothChartViewConfig::class);');
    expect($component)->toContain("'dentitionOptions' => \$viewConfig->dentitionOptions()");
    expect($viewConfig)->toContain("'Người lớn'");
    expect($viewConfig)->toContain("'Trẻ em'");
});

it('supports multi-select toggle for mobile and desktop in tooth chart component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothChart.php');
    $viewConfigPath = app_path('Support/ToothChartViewConfig.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);

    expect($blade)->toContain('multiSelectMode: false');
    expect($blade)->toContain('toggleMultiSelectMode()');
    expect($blade)->toContain('this.multiSelectMode || (event && (event.ctrlKey || event.metaKey))');
    expect($blade)->toContain('Chọn nhiều');
    expect($component)->toContain("'selectionHint' => \$viewConfig->selectionHint()");
    expect($viewConfig)->toContain('hỗ trợ mobile');
});

it('escapes diagnosis condition codes safely in tooth chart alpine expressions', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain(':class="hasCondition(@js((string) $condition->code)) ? \'bg-primary-50 dark:bg-primary-500/15\' : \'\'"')
        ->and($blade)->toContain('@click="toggleCondition(@js((string) $condition->code))"')
        ->and($blade)->toContain(':checked="hasCondition(@js((string) $condition->code))"')
        ->and($blade)->not->toContain("@click=\"toggleCondition('{{ \$condition->code }}')\"");
});

it('uses dark-mode friendly modal and legend styling in tooth chart component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $componentPath = app_path('Filament/Forms/Components/ToothChart.php');
    $viewConfigPath = app_path('Support/ToothChartViewConfig.php');
    $legendPartialPath = resource_path('views/filament/forms/components/partials/tooth-chart-legend.blade.php');
    $blade = File::get($bladePath);
    $component = File::get($componentPath);
    $viewConfig = File::get($viewConfigPath);
    $legendPartial = File::get($legendPartialPath);

    expect($blade)->toContain('class="crm-modal-close-btn"')
        ->and($blade)->toContain('dark:text-gray-100')
        ->and($blade)->toContain('dark:text-gray-400')
        ->and($blade)->toContain('dark:hover:bg-gray-800/70');
    expect($blade)->toContain("@include('filament.forms.components.partials.tooth-chart-legend', ['legendItems' => \$treatmentLegend])");
    expect($legendPartial)->toContain("@props(['legendItems'])");
    expect($legendPartial)->toContain('@foreach($legendItems as $legendItem)');
    expect($legendPartial)->toContain('dark:text-gray-300');
    expect($component)->toContain("'treatmentLegend' => \$viewConfig->treatmentLegend()");
    expect($viewConfig)->toContain('bg-red-500');
});
