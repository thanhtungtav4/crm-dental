<?php

use App\Livewire\PatientExamForm;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use Livewire\Livewire;

it('lists only assignable doctors in the patient exam form doctor search', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $allowedDoctor = User::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Allowed Doctor',
    ]);
    $allowedDoctor->assignRole('Doctor');

    $assignedDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Assigned Doctor',
    ]);
    $assignedDoctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $assignedDoctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->toDateString(),
        'created_by' => $manager->id,
    ]);

    $outsideDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Outside Doctor',
    ]);
    $outsideDoctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
    ]);

    $component = Livewire::actingAs($manager)
        ->test(PatientExamForm::class, ['patient' => $patient])
        ->instance();

    $doctorIds = $component->getDoctors()->pluck('id')->all();

    expect($doctorIds)->toContain($allowedDoctor->id, $assignedDoctor->id)
        ->not->toContain($outsideDoctor->id);
});

it('rejects outside-branch treating doctor selections in the patient exam form', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $allowedDoctor = User::factory()->create([
        'branch_id' => $branchA->id,
        'name' => 'Allowed Doctor',
    ]);
    $allowedDoctor->assignRole('Doctor');

    $outsideDoctor = User::factory()->create([
        'branch_id' => $branchB->id,
        'name' => 'Outside Doctor',
    ]);
    $outsideDoctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
    ]);

    Livewire::actingAs($manager)
        ->test(PatientExamForm::class, ['patient' => $patient])
        ->call('selectTreatingDoctor', $outsideDoctor->id)
        ->assertSet('treating_doctor_id', null)
        ->call('selectTreatingDoctor', $allowedDoctor->id)
        ->assertSet('treating_doctor_id', $allowedDoctor->id)
        ->assertSet('treatingDoctorSearch', $allowedDoctor->name);
});
