<?php

use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

it('blocks direct insurance claim delete attempts at model layer', function (): void {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 300000,
        'paid_amount' => 0,
    ]);

    $claim = InsuranceClaim::query()->create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'payer_name' => 'Bao hiem Bao Viet',
        'amount_claimed' => 250000,
        'status' => InsuranceClaim::STATUS_DRAFT,
    ]);

    expect(fn () => $claim->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
