<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('blocks non admin writes to unauthorized branch across customer patient plan appointment and invoice', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $actor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    $this->actingAs($actor);

    expect(fn () => Customer::query()->create([
        'branch_id' => $branchB->id,
        'full_name' => 'Blocked Customer',
        'phone' => '0908000001',
        'source' => 'walkin',
        'status' => 'lead',
    ]))->toThrow(ValidationException::class, 'khách hàng');

    $customer = Customer::factory()->create([
        'branch_id' => $branchA->id,
    ]);

    expect(fn () => Patient::query()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchB->id,
        'full_name' => 'Blocked Patient',
        'phone' => '0908000002',
        'email' => 'blocked-patient@example.test',
    ]))->toThrow(ValidationException::class, 'bệnh nhân');

    $patient = Patient::query()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    expect(fn () => TreatmentPlan::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchB->id,
        'status' => TreatmentPlan::STATUS_DRAFT,
        'title' => 'Blocked plan',
    ]))->toThrow(ValidationException::class, 'kế hoạch điều trị');

    $doctor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctor->assignRole('Doctor');

    expect(fn () => Appointment::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branchB->id,
        'date' => now()->addDay()->setTime(9, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]))->toThrow(ValidationException::class, 'lịch hẹn');

    expect(fn () => Invoice::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchB->id,
        'status' => Invoice::STATUS_DRAFT,
        'subtotal' => 500_000,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]))->toThrow(ValidationException::class, 'hóa đơn');
});

it('blocks non admin payment write when invoice belongs to unauthorized branch', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

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
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchB->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900_000,
        'paid_amount' => 0,
        'issued_at' => now(),
    ]);

    $actor = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $this->actingAs($actor);

    expect(fn () => $invoice->recordPayment(
        amount: 100_000,
        method: 'cash',
        notes: 'blocked payment',
        direction: 'receipt',
    ))->toThrow(ValidationException::class, 'phiếu thu');
});

it('allows admin to write data across branches', function () {
    $branchA = Branch::factory()->create(['active' => true]);
    $branchB = Branch::factory()->create(['active' => true]);

    $admin = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $customer = Customer::query()->create([
        'branch_id' => $branchB->id,
        'full_name' => 'Admin Allowed',
        'phone' => '0908000009',
        'source' => 'walkin',
        'status' => 'lead',
    ]);

    expect((int) $customer->branch_id)->toBe($branchB->id);
});
