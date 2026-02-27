<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ClinicSetting;
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
        'branch_id' => $branchA->id,
        'doctor_id' => $doctor->id,
        'created_by' => $doctor->id,
    ]);

    return [
        'branch_a' => $branchA,
        'branch_b' => $branchB,
        'patient' => $patient,
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

    $printAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $fixture['invoice']->id)
        ->where('action', AuditLog::ACTION_PRINT)
        ->latest('id')
        ->first();

    expect($printAudit)->not->toBeNull()
        ->and((string) data_get($printAudit?->metadata, 'channel'))->toBe('invoice_print')
        ->and((string) data_get($printAudit?->metadata, 'output'))->toBe('html');
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

    $exportAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $fixture['invoice']->id)
        ->where('action', AuditLog::ACTION_EXPORT)
        ->latest('id')
        ->first();

    expect($exportAudit)->not->toBeNull()
        ->and((string) data_get($exportAudit?->metadata, 'channel'))->toBe('invoice_export')
        ->and((bool) data_get($exportAudit?->metadata, 'is_exported'))->toBeTrue();
});

it('writes print audit logs for payment receipt and prescription print actions', function () {
    $fixture = makePrintFixture();

    $this->actingAs($fixture['manager_a']);

    $this->get(route('payments.print', ['payment' => $fixture['payment'], 'pdf' => 0]))
        ->assertSuccessful();
    $this->get(route('prescriptions.print', ['prescription' => $fixture['prescription'], 'pdf' => 0]))
        ->assertSuccessful();

    $paymentAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PAYMENT)
        ->where('entity_id', $fixture['payment']->id)
        ->where('action', AuditLog::ACTION_PRINT)
        ->latest('id')
        ->first();

    $prescriptionAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_PRESCRIPTION)
        ->where('entity_id', $fixture['prescription']->id)
        ->where('action', AuditLog::ACTION_PRINT)
        ->latest('id')
        ->first();

    expect($paymentAudit)->not->toBeNull()
        ->and((string) data_get($paymentAudit?->metadata, 'channel'))->toBe('payment_receipt_print')
        ->and($prescriptionAudit)->not->toBeNull()
        ->and((string) data_get($prescriptionAudit?->metadata, 'channel'))->toBe('prescription_print');
});

it('keeps prescription print branch isolation stable after patient transfer', function () {
    $fixture = makePrintFixture();
    $patient = $fixture['patient'];
    $prescription = $fixture['prescription'];

    $patient->update([
        'first_branch_id' => $fixture['branch_b']->id,
    ]);

    expect($prescription->fresh()?->resolveBranchId())->toBe($fixture['branch_a']->id);

    $this->actingAs($fixture['manager_b']);
    $this->get(route('prescriptions.print', ['prescription' => $prescription, 'pdf' => 0]))
        ->assertForbidden();

    $this->actingAs($fixture['manager_a']);
    $this->get(route('prescriptions.print', ['prescription' => $prescription, 'pdf' => 0]))
        ->assertSuccessful();
});

it('renders clinic branding metadata in printable templates', function () {
    $fixture = makePrintFixture();

    ClinicSetting::setValue('branding.clinic_name', 'Nha Khoa Production', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.logo_url', 'https://example.com/clinic-logo.png', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.address', '123 Tran Hung Dao, HCM', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.phone', '0909000999', ['value_type' => 'text']);
    ClinicSetting::setValue('branding.email', 'clinic@example.com', ['value_type' => 'text']);

    $this->actingAs($fixture['manager_a']);

    $this->get(route('invoices.print', ['invoice' => $fixture['invoice'], 'pdf' => 0]))
        ->assertSuccessful()
        ->assertSee('NHA KHOA PRODUCTION')
        ->assertSee('123 Tran Hung Dao, HCM')
        ->assertSee('0909000999')
        ->assertSee('clinic@example.com')
        ->assertSee('https://example.com/clinic-logo.png', false);

    $this->get(route('payments.print', ['payment' => $fixture['payment'], 'pdf' => 0]))
        ->assertSuccessful()
        ->assertSee('NHA KHOA PRODUCTION')
        ->assertSee('https://example.com/clinic-logo.png', false);

    $this->get(route('prescriptions.print', ['prescription' => $fixture['prescription'], 'pdf' => 0]))
        ->assertSuccessful()
        ->assertSee('NHA KHOA PRODUCTION')
        ->assertSee('123 Tran Hung Dao, HCM')
        ->assertSee('https://example.com/clinic-logo.png', false);
});
