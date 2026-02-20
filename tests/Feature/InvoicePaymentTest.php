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

it('tracks refund as negative payment and recalculates balance', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $invoice->recordPayment(900000, 'cash');
    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(900000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe('partial');

    $invoice->recordPayment(200000, 'transfer', 'Hoàn tiền', now(), 'refund', 'Điều chỉnh dịch vụ');

    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(700000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe('partial')
        ->and($invoice->fresh()->calculateBalance())->toEqualWithDelta(300000.00, 0.01);
});

