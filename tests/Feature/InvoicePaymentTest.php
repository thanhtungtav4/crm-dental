<?php

use App\Models\Invoice;
use App\Models\Patient;

it('tracks invoice payments and updates status', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $invoice->recordPayment(400000, 'cash');
    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(400000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe('partial');

    $invoice->recordPayment(600000, 'transfer');
    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(1000000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($invoice->fresh()->isPaid())->toBeTrue();
});


