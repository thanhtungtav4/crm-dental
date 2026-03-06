<?php

use Illuminate\Support\Facades\File;

it('removes force delete and restore surfaces from treatment edit pages', function (): void {
    $planPage = File::get(app_path('Filament/Resources/TreatmentPlans/Pages/EditTreatmentPlan.php'));
    $planItemPage = File::get(app_path('Filament/Resources/PlanItems/Pages/EditPlanItem.php'));
    $sessionPage = File::get(app_path('Filament/Resources/TreatmentSessions/Pages/EditTreatmentSession.php'));

    expect($planPage)
        ->toContain('TreatmentDeletionGuardService')
        ->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($planItemPage)
        ->toContain('TreatmentDeletionGuardService')
        ->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($sessionPage)
        ->toContain('TreatmentDeletionGuardService')
        ->toContain('DeleteAction::make()');
});

it('removes bulk destructive actions from treatment tables', function (): void {
    $plansTable = File::get(app_path('Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php'));
    $planItemsTable = File::get(app_path('Filament/Resources/PlanItems/Tables/PlanItemsTable.php'));
    $sessionsTable = File::get(app_path('Filament/Resources/TreatmentSessions/Tables/TreatmentSessionsTable.php'));

    expect($plansTable)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');

    expect($planItemsTable)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');

    expect($sessionsTable)
        ->not->toContain('DeleteBulkAction::make()');
});

it('keeps plan item delete guarded only in the canonical relation manager', function (): void {
    $canonicalRelationManager = File::get(app_path('Filament/Resources/TreatmentPlans/RelationManagers/PlanItemsRelationManager.php'));
    $resource = File::get(app_path('Filament/Resources/TreatmentPlans/TreatmentPlanResource.php'));

    expect($canonicalRelationManager)
        ->toContain('TreatmentDeletionGuardService')
        ->toContain('DeleteAction::make()')
        ->not->toContain('DeleteBulkAction::make()');

    expect($resource)
        ->toContain('RelationManagers\\PlanItemsRelationManager::class')
        ->not->toContain('Relations\\PlanItemsRelationManager::class')
        ->not->toContain('Relations\\SessionsRelationManager::class');

    expect(File::exists(app_path('Filament/Resources/TreatmentPlans/Relations/PlanItemsRelationManager.php')))->toBeFalse()
        ->and(File::exists(app_path('Filament/Resources/TreatmentPlans/Relations/SessionsRelationManager.php')))->toBeFalse();
});
