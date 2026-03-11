<?php

use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

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
    $appointmentForm = File::get(app_path('Filament/Resources/Appointments/Schemas/AppointmentForm.php'));
    $createCustomerPage = File::get(app_path('Filament/Resources/Customers/Pages/CreateCustomer.php'));
    $editCustomerPage = File::get(app_path('Filament/Resources/Customers/Pages/EditCustomer.php'));
    $createPatientPage = File::get(app_path('Filament/Resources/Patients/Pages/CreatePatient.php'));
    $editPatientPage = File::get(app_path('Filament/Resources/Patients/Pages/EditPatient.php'));

    expect($createCustomerPage)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizeCustomerFormData');

    expect($appointmentForm)
        ->toContain('PatientAssignmentAuthorizer::class')
        ->toContain('sanitizeCustomerFormData')
        ->toContain("data_get(\$component->getLivewire(), 'data.branch_id')");

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

it('creates inline appointment leads in the selected branch', function (): void {
    $branchOne = Branch::factory()->create();
    $branchTwo = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branchOne->id,
    ]);
    $admin->assignRole('Admin');

    $phone = fake()->unique()->numerify('09########');
    $email = fake()->unique()->safeEmail();

    Livewire::actingAs($admin)
        ->test(CreateAppointment::class)
        ->set('data.branch_id', $branchTwo->id)
        ->mountFormComponentAction('customer_id', 'createOption')
        ->setFormComponentActionData([
            'full_name' => 'Inline branch customer',
            'phone' => $phone,
            'email' => $email,
            'gender' => 'female',
        ])
        ->callMountedFormComponentAction()
        ->assertHasNoFormComponentActionErrors();

    $customer = Customer::query()
        ->where('email_search_hash', Customer::emailSearchHash($email))
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer?->branch_id)->toBe($branchTwo->id)
        ->and($customer?->assigned_to)->toBeNull();
});

it('blocks duplicate email when creating inline appointment leads', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $existingCustomer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateAppointment::class)
        ->set('data.branch_id', $branch->id)
        ->mountFormComponentAction('customer_id', 'createOption')
        ->setFormComponentActionData([
            'full_name' => 'Inline duplicate email',
            'phone' => fake()->unique()->numerify('09########'),
            'email' => $existingCustomer->email,
            'gender' => 'female',
        ])
        ->callMountedFormComponentAction()
        ->assertHasFormComponentActionErrors();

    expect(
        Customer::withTrashed()
            ->where('email_search_hash', Customer::emailSearchHash((string) $existingCustomer->email))
            ->count()
    )->toBe(1)
        ->and(
            Customer::withTrashed()
                ->where('full_name', 'Inline duplicate email')
                ->count()
        )->toBe(0);
});
