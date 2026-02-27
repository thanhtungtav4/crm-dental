<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientBranchTransferService;
use Illuminate\Validation\ValidationException;

it('allows appointment when doctor is assigned to multiple branches', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => true,
        'assigned_from' => today()->subDay()->toDateString(),
    ]);

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchB->id,
        'is_active' => true,
        'is_primary' => false,
        'assigned_from' => today()->subDay()->toDateString(),
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branchB->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchB->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $appointment = Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branchB->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    expect($appointment->exists)->toBeTrue()
        ->and((int) $appointment->branch_id)->toBe($branchB->id);
});

it('blocks appointment when doctor is not assigned to target branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->create([
        'user_id' => $doctor->id,
        'branch_id' => $branchA->id,
        'is_active' => true,
        'is_primary' => true,
        'assigned_from' => today()->subDay()->toDateString(),
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branchB->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchB->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    expect(fn () => Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branchB->id,
        'date' => now()->addDay()->setTime(10, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class, 'chưa được phân công');
});

it('transfers patient branch with request log branch log and audit log', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $this->actingAs($manager);

    $transferRequest = app(PatientBranchTransferService::class)->transferDirect(
        patient: $patient,
        toBranchId: $branchB->id,
        actorId: $manager->id,
        reason: 'Khách chuyển nơi ở',
        note: 'Tiếp nhận theo yêu cầu lễ tân',
    );

    expect($transferRequest->status)->toBe('applied')
        ->and((int) $transferRequest->from_branch_id)->toBe($branchA->id)
        ->and((int) $transferRequest->to_branch_id)->toBe($branchB->id)
        ->and($patient->fresh()?->first_branch_id)->toBe($branchB->id);

    $branchLog = BranchLog::query()
        ->where('patient_id', $patient->id)
        ->latest('id')
        ->first();

    expect($branchLog)->not->toBeNull()
        ->and((int) $branchLog->from_branch_id)->toBe($branchA->id)
        ->and((int) $branchLog->to_branch_id)->toBe($branchB->id)
        ->and((string) $branchLog->note)->toContain('Ly do: Khách chuyển nơi ở');

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_BRANCH_TRANSFER)
        ->where('action', AuditLog::ACTION_TRANSFER)
        ->where('entity_id', $transferRequest->id)
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and((int) data_get($auditLog->metadata, 'patient_id'))->toBe($patient->id);
});

it('requires action permission to transfer patient branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $this->actingAs($doctor);

    expect(fn () => app(PatientBranchTransferService::class)->transferDirect(
        patient: $patient,
        toBranchId: $branchB->id,
        actorId: $doctor->id,
        reason: 'Test unauthorized transfer',
        note: null,
    ))->toThrow(ValidationException::class, 'không có quyền chuyển bệnh nhân liên chi nhánh');
});
