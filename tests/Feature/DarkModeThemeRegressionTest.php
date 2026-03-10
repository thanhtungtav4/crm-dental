<?php

use Illuminate\Support\Facades\File;

it('keeps the admin theme on the updated dark-mode palette', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)->toContain('.dark {')
        ->and($css)->toContain('--crm-bg: #07111f;')
        ->and($css)->toContain('--crm-surface-elevated: #15263f;')
        ->and($css)->toContain('--crm-primary: #60a5fa;')
        ->and($css)->not->toContain('#7c6cf6');
});

it('does not leave the old purple fallback in custom tooth views', function (): void {
    $toothChartBlade = File::get(resource_path('views/filament/forms/components/tooth-chart.blade.php'));
    $patientExamBlade = File::get(resource_path('views/livewire/patient-exam-form.blade.php'));

    expect($toothChartBlade)->not->toContain('#7c6cf6')
        ->and($patientExamBlade)->not->toContain('#7c6cf6')
        ->and($toothChartBlade)->toContain('var(--crm-primary, #2563eb)')
        ->and($patientExamBlade)->toContain('var(--crm-primary, #2563eb)');
});
