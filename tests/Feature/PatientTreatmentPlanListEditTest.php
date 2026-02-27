<?php

use Illuminate\Support\Facades\File;

it('keeps patient context when opening plan item edit from treatment plan list', function (): void {
    $bladePath = resource_path('views/livewire/patient-treatment-plan-section.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain("'return_url' => request()->fullUrl()");
});

it('exposes detailed fields in plan item edit form for treatment workflow', function (): void {
    $formPath = app_path('Filament/Resources/PlanItems/Schemas/PlanItemForm.php');
    $form = File::get($formPath);

    expect($form)->toContain("Select::make('diagnosis_ids')")
        ->and($form)->toContain("Select::make('approval_status')")
        ->and($form)->toContain("TextInput::make('discount_percent')")
        ->and($form)->toContain("TextInput::make('discount_amount')")
        ->and($form)->toContain("TextInput::make('vat_amount')")
        ->and($form)->toContain("TextInput::make('final_amount')")
        ->and($form)->toContain("Select::make('status')");
});

it('redirects plan item edit page back to return url after save', function (): void {
    $pagePath = app_path('Filament/Resources/PlanItems/Pages/EditPlanItem.php');
    $page = File::get($pagePath);

    expect($page)->toContain('protected function getRedirectUrl(): string')
        ->and($page)->toContain("request()->query('return_url')")
        ->and($page)->toContain('str_starts_with($returnUrl, \'/\')');
});
