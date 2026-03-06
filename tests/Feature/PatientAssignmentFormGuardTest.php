<?php

use Illuminate\Support\Facades\File;

it('binds customer and patient forms to assignment scope options', function (): void {
    $customerForm = File::get(app_path('Filament/Resources/Customers/Schemas/CustomerForm.php'));
    $patientForm = File::get(app_path('Filament/Resources/Patients/Schemas/PatientForm.php'));

    expect($customerForm)
        ->toContain('PatientAssignmentAuthorizer')
        ->toContain('assignableStaffOptions')
        ->toContain('scopeAssignableStaff');

    expect($patientForm)
        ->toContain('PatientAssignmentAuthorizer')
        ->toContain('assignableStaffOptions')
        ->toContain('assignableDoctorOptions')
        ->toContain('scopeAssignableDoctors');
});

it('guards customer and patient create edit pages with assignment sanitization', function (): void {
    $createCustomerPage = File::get(app_path('Filament/Resources/Customers/Pages/CreateCustomer.php'));
    $editCustomerPage = File::get(app_path('Filament/Resources/Customers/Pages/EditCustomer.php'));
    $createPatientPage = File::get(app_path('Filament/Resources/Patients/Pages/CreatePatient.php'));
    $editPatientPage = File::get(app_path('Filament/Resources/Patients/Pages/EditPatient.php'));

    expect($createCustomerPage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizeCustomerFormData');

    expect($editCustomerPage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizeCustomerFormData')
        ->toContain('mutateFormDataBeforeSave');

    expect($createPatientPage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizePatientFormData');

    expect($editPatientPage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizePatientFormData')
        ->toContain('mutateFormDataBeforeSave');
});
