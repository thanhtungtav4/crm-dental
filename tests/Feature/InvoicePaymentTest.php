<?php

use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Support\Facades\Artisan;

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

it('persists overdue status with command and keeps cancelled invoice unchanged', function () {
    $patient = Patient::factory()->create();

    $overdueInvoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $cancelledInvoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'cancelled',
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    Artisan::call('invoices:sync-overdue-status');

    expect($overdueInvoice->fresh()->status)->toBe('overdue')
        ->and($cancelledInvoice->fresh()->status)->toBe('cancelled');
});

it('moves overdue invoice to paid when fully collected', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 800000,
        'paid_amount' => 0,
        'status' => 'overdue',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $invoice->recordPayment(800000, 'cash');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(800000.00, 0.01);
});

it('is idempotent when submitting duplicated transaction_ref', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1200000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $first = $invoice->recordPayment(
        300000,
        'transfer',
        'Thanh toán lần 1',
        now(),
        'receipt',
        null,
        'TX-INV-001'
    );

    $second = $invoice->recordPayment(
        300000,
        'transfer',
        'Retry request',
        now(),
        'receipt',
        null,
        'TX-INV-001'
    );

    expect($first->id)->toBe($second->id)
        ->and($invoice->payments()->count())->toBe(1)
        ->and((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(300000.00, 0.01);
});

it('keeps aggregate paid amount correct with mixed receipts and refunds', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'total_amount' => 1500000,
        'paid_amount' => 0,
        'status' => 'issued',
    ]);

    $invoice->recordPayment(700000, 'cash', 'Đợt 1', now(), 'receipt', null, 'TX-A');
    $invoice->recordPayment(400000, 'transfer', 'Đợt 2', now(), 'receipt', null, 'TX-B');
    $invoice->recordPayment(200000, 'cash', 'Hoàn một phần', now(), 'refund', 'Điều chỉnh dịch vụ', 'TX-C');

    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(900000.00, 0.01)
        ->and($invoice->fresh()->calculateBalance())->toEqualWithDelta(600000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe('partial');
});
