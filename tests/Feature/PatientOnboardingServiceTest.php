<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientOnboardingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;

it('creates a patient and companion customer through the onboarding service', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = app(PatientOnboardingService::class)->create([
        'first_branch_id' => $branch->id,
        'full_name' => 'Patient Onboarding',
        'phone' => '0901444555',
        'email' => 'onboarding@example.test',
        'status' => 'active',
        'first_visit_reason' => 'Kham tong quat',
        'owner_staff_id' => $manager->id,
    ]);

    $patient->refresh();
    $customer = $patient->customer;

    expect($customer)->not->toBeNull()
        ->and($patient->customer_id)->toBe($customer->id)
        ->and($customer->branch_id)->toBe($branch->id)
        ->and($customer->phone)->toBe('0901444555')
        ->and($customer->email)->toBe('onboarding@example.test')
        ->and($customer->assigned_to)->toBe($manager->id)
        ->and($customer->status)->toBe('lead');
});

it('rolls back companion customer creation when patient persistence fails', function (): void {
    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    Patient::factory()->create([
        'patient_code' => 'PAT-COLLISION-001',
    ]);

    expect(fn () => app(PatientOnboardingService::class)->create([
        'first_branch_id' => $branch->id,
        'patient_code' => 'PAT-COLLISION-001',
        'full_name' => 'Patient Rollback',
        'phone' => '0901666888',
        'email' => 'rollback@example.test',
        'status' => 'active',
    ]))->toThrow(QueryException::class);

    expect(Customer::query()
        ->where('phone_search_hash', Customer::phoneSearchHash('0901666888'))
        ->exists())->toBeFalse();
});

it('wires create patient page to the onboarding service instead of direct model side effects', function (): void {
    $page = File::get(app_path('Filament/Resources/Patients/Pages/CreatePatient.php'));
    $patientModel = File::get(app_path('Models/Patient.php'));

    expect($page)
        ->toContain('handleRecordCreation')
        ->toContain('PatientOnboardingService::class');

    expect($patientModel)
        ->toContain('luồng onboarding')
        ->not->toContain('Auto-created from Patient');
});
