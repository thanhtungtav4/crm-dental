<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\ClinicSetting;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Validation\ValidationException;

it('keeps payment idempotency stable under stress submissions with same transaction ref', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'invoice_no' => null,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 600000,
        'paid_amount' => 0,
        'issued_at' => now(),
    ]);

    $invoiceId = (int) $invoice->id;
    $transactionRef = 'STRESS-TXN-001';

    $tasks = [];
    for ($attempt = 0; $attempt < 40; $attempt++) {
        $tasks[] = static fn (): int => (int) Invoice::query()
            ->findOrFail($invoiceId)
            ->recordPayment(
                amount: 200000,
                method: 'cash',
                notes: 'stress duplicate submit',
                transactionRef: $transactionRef,
            )
            ->id;
    }

    $results = Concurrency::driver('sync')->run($tasks);

    $matchingPayments = Payment::query()
        ->where('invoice_id', $invoiceId)
        ->where('transaction_ref', $transactionRef)
        ->get();

    expect($matchingPayments->count())->toBe(1)
        ->and(collect($results)->filter()->unique()->count())->toBe(1)
        ->and((float) Invoice::query()->findOrFail($invoiceId)->paid_amount)->toEqualWithDelta(200000.0, 0.01);
});

it('keeps invoice installment claim code generation unique under stress loops', function () {
    $patient = Patient::factory()->create();

    $invoices = collect();
    for ($attempt = 1; $attempt <= 50; $attempt++) {
        $invoices->push(Invoice::factory()->create([
            'patient_id' => $patient->id,
            'invoice_no' => null,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100000 + $attempt,
            'paid_amount' => 0,
            'issued_at' => now(),
        ]));
    }

    $invoiceCodes = $invoices->pluck('invoice_no')->filter()->values();
    expect($invoiceCodes->count())->toBe(50)
        ->and($invoiceCodes->unique()->count())->toBe(50);

    $plans = $invoices->take(25)->map(function (Invoice $invoice) use ($patient): InstallmentPlan {
        return InstallmentPlan::query()->create([
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'branch_id' => $patient->first_branch_id,
            'plan_code' => null,
            'financed_amount' => 100000,
            'down_payment_amount' => 0,
            'remaining_amount' => 100000,
            'number_of_installments' => 2,
            'installment_amount' => 50000,
            'start_date' => now()->toDateString(),
            'status' => InstallmentPlan::STATUS_ACTIVE,
            'schedule' => [],
        ]);
    });

    $planCodes = $plans->pluck('plan_code')->filter()->values();
    expect($planCodes->count())->toBe(25)
        ->and($planCodes->unique()->count())->toBe(25);

    $claims = $invoices->skip(25)->take(25)->map(function (Invoice $invoice) use ($patient): InsuranceClaim {
        return InsuranceClaim::query()->create([
            'invoice_id' => $invoice->id,
            'patient_id' => $patient->id,
            'claim_number' => null,
            'payer_name' => 'Insurance Probe',
            'amount_claimed' => 50000,
            'amount_approved' => 0,
            'status' => InsuranceClaim::STATUS_DRAFT,
        ]);
    });

    $claimCodes = $claims->pluck('claim_number')->filter()->values();
    expect($claimCodes->count())->toBe(25)
        ->and($claimCodes->unique()->count())->toBe(25);
});

it('keeps overpay and overbooking policy guards enforced under repeated attempts', function () {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    setFinancePolicyForConcurrencyStress('finance.allow_overpay', false);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'invoice_no' => null,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 500000,
        'paid_amount' => 0,
        'issued_at' => now(),
    ]);

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        expect(fn () => $invoice->recordPayment(
            amount: 700000,
            method: 'cash',
            notes: 'overpay guard stress',
            transactionRef: 'OVERPAY-STRESS-'.$attempt,
        ))->toThrow(ValidationException::class, 'overpay');
    }

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay()->setTime(9, 0),
        'duration_minutes' => 30,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    BranchOverbookingPolicy::query()->updateOrCreate(
        ['branch_id' => $branch->id],
        [
            'is_enabled' => true,
            'max_parallel_per_doctor' => 1,
            'require_override_reason' => true,
        ],
    );

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        expect(fn () => Appointment::query()->create([
            'customer_id' => $customer->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'date' => now()->addDay()->setTime(9, 10),
            'duration_minutes' => 30,
            'status' => Appointment::STATUS_SCHEDULED,
        ]))->toThrow(ValidationException::class, 'lý do override');
    }
});

function setFinancePolicyForConcurrencyStress(string $key, bool $value): void
{
    ClinicSetting::setValue($key, $value, [
        'group' => 'finance',
        'label' => $key,
        'value_type' => 'boolean',
    ]);
}
