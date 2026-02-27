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
