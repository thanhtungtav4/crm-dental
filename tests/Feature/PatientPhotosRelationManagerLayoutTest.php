<?php

use Illuminate\Support\Facades\File;

it('sets a visible actions column label for patient photo table', function (): void {
    $phpPath = app_path('Filament/Resources/Patients/RelationManagers/PatientPhotosRelationManager.php');
    $php = File::get($phpPath);

    expect($php)
        ->toContain("->actionsColumnLabel('Thao tác')");
});

it('constrains photos tab actions column width to prevent blank oversized header cell', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)
        ->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table th.fi-ta-actions-header-cell')
        ->and($css)->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table td.fi-ta-actions-cell')
        ->and($css)->toContain('width: 116px;')
        ->and($css)->toContain('min-width: 116px;')
        ->and($css)->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table .fi-ta-actions {')
        ->and($css)->toContain('justify-content: center;');
});
