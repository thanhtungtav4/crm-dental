<?php

use Illuminate\Support\Facades\File;

it('uses wide modal and structured layout for patient prescription relation manager', function (): void {
    $phpPath = app_path('Filament/Resources/Patients/RelationManagers/PrescriptionsRelationManager.php');
    $php = File::get($phpPath);

    expect($php)->toContain('->columns(1)')
        ->and($php)->toContain("Section::make('Chi tiết thuốc')")
        ->and($php)->toContain('->columnSpanFull()')
        ->and($php)->toContain("Repeater::make('items')")
        ->and($php)->toContain('->columns(12)')
        ->and($php)->toContain("->modalWidth('7xl')");
});

it('keeps medication item fields balanced to avoid narrow broken inputs', function (): void {
    $phpPath = app_path('Filament/Resources/Patients/RelationManagers/PrescriptionsRelationManager.php');
    $php = File::get($phpPath);

    expect($php)->toContain("TextInput::make('medication_name')")
        ->and($php)->toContain('->columnSpan(4)')
        ->and($php)->toContain("TextInput::make('dosage')")
        ->and($php)->toContain("TextInput::make('quantity')")
        ->and($php)->toContain("Select::make('unit')")
        ->and($php)->toContain("Select::make('instructions')")
        ->and($php)->toContain("TextInput::make('duration')")
        ->and($php)->toContain("TextInput::make('notes')")
        ->and($php)->toContain('->columnSpanFull()');
});

it('keeps prescription tab table full-width and constrains actions column width', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)
        ->toContain('.crm-rel-tab.crm-rel-tab-prescriptions .fi-ta-table {')
        ->and($css)->toContain('width: 100%;')
        ->and($css)->toContain('min-width: 100%;')
        ->and($css)->toContain('th.fi-ta-actions-header-cell')
        ->and($css)->toContain('td.fi-ta-actions-cell')
        ->and($css)->toContain('width: 124px;')
        ->and($css)->toContain('.crm-rel-tab.crm-rel-tab-prescriptions .fi-ta-table .fi-ta-actions {')
        ->and($css)->toContain('justify-content: flex-end;');
});
