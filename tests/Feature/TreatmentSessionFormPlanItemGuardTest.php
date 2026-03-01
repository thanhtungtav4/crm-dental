<?php

use Illuminate\Support\Facades\File;

it('binds treatment session form to treatment plan and plan item workflow', function (): void {
    $formPath = app_path('Filament/Resources/TreatmentSessions/Schemas/TreatmentSessionForm.php');
    $form = File::get($formPath);

    expect($form)
        ->toContain("Select::make('treatment_plan_id')")
        ->toContain("Select::make('plan_item_id')")
        ->toContain('scopeTreatmentPlanQueryForCurrentUser')
        ->toContain('resolveDefaultTreatmentPlanId')
        ->toContain('planItemOptionsForPlan')
        ->toContain("->required(fn (string \$operation): bool => \$operation === 'create')");
});

it('guards create and edit treatment session against invalid plan item mapping', function (): void {
    $createPath = app_path('Filament/Resources/TreatmentSessions/Pages/CreateTreatmentSession.php');
    $editPath = app_path('Filament/Resources/TreatmentSessions/Pages/EditTreatmentSession.php');

    $createPage = File::get($createPath);
    $editPage = File::get($editPath);

    expect($createPage)
        ->toContain('mutateFormDataBeforeCreate')
        ->toContain('BranchAccess::assertCanAccessBranch')
        ->toContain('Hạng mục điều trị không thuộc kế hoạch đã chọn.');

    expect($editPage)
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('BranchAccess::assertCanAccessBranch')
        ->toContain('Hạng mục điều trị không thuộc kế hoạch đã chọn.');
});
