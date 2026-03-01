<?php

use Illuminate\Support\Facades\File;

it('uses segmented dentition controls in tooth chart component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('class="crm-dentition-toggle" role="tablist"');
    expect($blade)->toContain('class="crm-dentition-option"');
    expect($blade)->toContain("dentitionMode === 'adult' ? 'is-active' : ''");
    expect($blade)->toContain("dentitionMode === 'child' ? 'is-active' : ''");
    expect($blade)->toContain('min-width: 78px; height: 30px; padding: 0 10px;');
    expect($blade)->toContain('background: var(--crm-primary, #7c6cf6); color: #fff;');
    expect($blade)->toContain('Người lớn');
    expect($blade)->toContain('Trẻ em');
});

it('supports multi-select toggle for mobile and desktop in tooth chart component', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('multiSelectMode: false');
    expect($blade)->toContain('toggleMultiSelectMode()');
    expect($blade)->toContain('this.multiSelectMode || (event && (event.ctrlKey || event.metaKey))');
    expect($blade)->toContain('Chọn nhiều');
    expect($blade)->toContain('hỗ trợ mobile');
});

it('escapes diagnosis condition codes safely in tooth chart alpine expressions', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-chart.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain(':class="hasCondition(@js((string) $condition->code)) ? \'bg-primary-50\' : \'\'"')
        ->and($blade)->toContain('@click="toggleCondition(@js((string) $condition->code))"')
        ->and($blade)->toContain(':checked="hasCondition(@js((string) $condition->code))"')
        ->and($blade)->not->toContain("@click=\"toggleCondition('{{ \$condition->code }}')\"");
});
