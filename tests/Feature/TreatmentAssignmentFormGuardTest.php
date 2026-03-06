<?php

use Illuminate\Support\Facades\File;

it('wires treatment plan form and pages through treatment assignment authorizer', function (): void {
    $form = File::get(app_path('Filament/Resources/TreatmentPlans/Schemas/TreatmentPlanForm.php'));
    $createPage = File::get(app_path('Filament/Resources/TreatmentPlans/Pages/CreateTreatmentPlan.php'));
    $editPage = File::get(app_path('Filament/Resources/TreatmentPlans/Pages/EditTreatmentPlan.php'));

    expect($form)
        ->toContain('TreatmentAssignmentAuthorizer')
        ->toContain('assignableDoctorOptions')
        ->toContain('isAssignableDoctorId')
        ->toContain("Select::make('doctor_id')");

    expect($createPage)
        ->toContain('TreatmentAssignmentAuthorizer::class')
        ->toContain('sanitizeTreatmentPlanFormData');

    expect($editPage)
        ->toContain('TreatmentAssignmentAuthorizer::class')
        ->toContain('sanitizeTreatmentPlanFormData');
});

it('wires treatment session form and pages through treatment assignment authorizer', function (): void {
    $form = File::get(app_path('Filament/Resources/TreatmentSessions/Schemas/TreatmentSessionForm.php'));
    $createPage = File::get(app_path('Filament/Resources/TreatmentSessions/Pages/CreateTreatmentSession.php'));
    $editPage = File::get(app_path('Filament/Resources/TreatmentSessions/Pages/EditTreatmentSession.php'));

    expect($form)
        ->toContain('TreatmentAssignmentAuthorizer')
        ->toContain('assignableDoctorOptions')
        ->toContain('assignableStaffOptions')
        ->toContain('resolveTreatmentPlanBranchId')
        ->toContain("Select::make('doctor_id')")
        ->toContain("Select::make('assistant_id')");

    expect($createPage)
        ->toContain('TreatmentAssignmentAuthorizer::class')
        ->toContain('sanitizeTreatmentSessionFormData');

    expect($editPage)
        ->toContain('TreatmentAssignmentAuthorizer::class')
        ->toContain('sanitizeTreatmentSessionFormData');
});

it('makes treatment material actor server owned', function (): void {
    $form = File::get(app_path('Filament/Resources/TreatmentMaterials/Schemas/TreatmentMaterialForm.php'));
    $service = File::get(app_path('Services/TreatmentMaterialUsageService.php'));
    $authorizer = File::get(app_path('Services/TreatmentAssignmentAuthorizer.php'));

    expect($form)
        ->toContain("Select::make('used_by')")
        ->toContain('->dehydrated(false)');

    expect($service)
        ->toContain('sanitizeTreatmentMaterialUsageData');

    expect($authorizer)
        ->toContain('sanitizeTreatmentMaterialUsageData')
        ->toContain("field: 'used_by'");
});
