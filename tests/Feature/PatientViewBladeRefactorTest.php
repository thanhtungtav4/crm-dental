<?php

use Illuminate\Support\Facades\File;

it('keeps patient view blade render-only without direct query logic', function (): void {
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->not->toContain('@php');
    expect($blade)->not->toContain('TreatmentMaterial::query');
    expect($blade)->not->toContain('->count(');
    expect($blade)->not->toContain('->sum(');
});

