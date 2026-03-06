<?php

use Illuminate\Support\Facades\File;

it('binds user form to provisioning authorizer for branch and permission scope', function (): void {
    $formPath = app_path('Filament/Resources/Users/Schemas/UserForm.php');
    $form = File::get($formPath);

    expect($form)
        ->toContain('UserProvisioningAuthorizer')
        ->toContain('scopeAssignableBranches')
        ->toContain('assignableBranchOptions')
        ->toContain('scopeAssignableRoles')
        ->toContain('canManageRoles')
        ->toContain('scopeAssignablePermissions')
        ->toContain('canManageDirectPermissions');
});

it('guards create and edit user pages with provisioning payload sanitization', function (): void {
    $createPath = app_path('Filament/Resources/Users/Pages/CreateUser.php');
    $editPath = app_path('Filament/Resources/Users/Pages/EditUser.php');

    $createPage = File::get($createPath);
    $editPage = File::get($editPath);

    expect($createPage)
        ->toContain('mutateFormDataBeforeCreate')
        ->toContain('UserProvisioningAuthorizer::class')
        ->toContain('sanitizeFormData');

    expect($editPage)
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('UserProvisioningAuthorizer::class')
        ->toContain('sanitizeFormData');
});
