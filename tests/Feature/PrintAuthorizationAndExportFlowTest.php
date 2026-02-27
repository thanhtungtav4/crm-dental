<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;

function makePrintFixture(): array
{
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $managerA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $managerA->assignRole('Manager');

    $managerB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $managerB->assignRole('Manager');

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
    ]);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_200_000,
        'paid_amount' => 0,
        'invoice_exported' => false,
        'exported_at' => null,
    ]);

    $invoice->recordPayment(
        amount: 300_000,
        method: 'cash',
        notes: 'Phiếu thu kiểm thử',
        direction: 'receipt',
        receivedBy: $managerA->id,
    );

    /** @var Payment $payment */
    $payment = Payment::query()->latest('id')->firstOrFail();

    $prescription = Prescription::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'created_by' => $doctor->id,
    ]);

    return [
        'manager_a' => $managerA,
        'manager_b' => $managerB,
        'invoice' => $invoice->fresh(),
        'payment' => $payment,
        'prescription' => $prescription,
    ];
}

it('forbids cross branch print access for invoice payment and prescription', function () {
    $fixture = makePrintFixture();

    $this->actingAs($fixture['manager_b']);

    $this->get(route('invoices.print', ['invoice' => $fixture['invoice'], 'pdf' => 0]))
        ->assertForbidden();

    $this->get(route('payments.print', ['payment' => $fixture['payment'], 'pdf' => 0]))
        ->assertForbidden();

    $this->get(route('prescriptions.print', ['prescription' => $fixture['prescription'], 'pdf' => 0]))
        ->assertForbidden();
});

it('does not mutate export flags when rendering invoice print via get', function () {
    $fixture = makePrintFixture();

    $this->actingAs($fixture['manager_a']);

    $this->get(route('invoices.print', ['invoice' => $fixture['invoice'], 'pdf' => 0]))
        ->assertSuccessful();

    $invoice = $fixture['invoice']->fresh();

    expect($invoice)->not->toBeNull()
        ->and($invoice?->invoice_exported)->toBeFalse()
        ->and($invoice?->exported_at)->toBeNull();
});

it('marks invoice as exported through explicit post endpoint', function () {
    $fixture = makePrintFixture();

    $this->actingAs($fixture['manager_a']);

    $this->postJson(route('invoices.mark-exported', $fixture['invoice']))
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('invoice_exported', true);

    $invoice = $fixture['invoice']->fresh();

    expect($invoice)->not->toBeNull()
        ->and($invoice?->invoice_exported)->toBeTrue()
        ->and($invoice?->exported_at)->not->toBeNull();
});
