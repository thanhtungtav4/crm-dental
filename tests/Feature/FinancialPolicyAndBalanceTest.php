<?php

use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\ClinicSetting;
use App\Models\Invoice;
use App\Models\Patient;
use Illuminate\Validation\ValidationException;

it('enforces overpay policy and allows when policy enabled', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    setFinancePolicy('finance.allow_overpay', false);

    expect(fn () => $invoice->recordPayment(1200000, 'cash'))
        ->toThrow(ValidationException::class, 'overpay');

    setFinancePolicy('finance.allow_overpay', true);

    $invoice->recordPayment(1200000, 'cash');

    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(1200000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

it('enforces draft prepay policy', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_DRAFT,
        'total_amount' => 500000,
        'paid_amount' => 0,
    ]);

    setFinancePolicy('finance.allow_prepay_draft', false);

    expect(fn () => $invoice->recordPayment(100000, 'cash'))
        ->toThrow(ValidationException::class, 'hóa đơn nháp');

    setFinancePolicy('finance.allow_prepay_draft', true);

    $invoice->recordPayment(100000, 'cash');

    expect((float) $invoice->fresh()->paid_amount)->toEqualWithDelta(100000.00, 0.01)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PARTIAL);
});

it('enforces deposit policy and persists deposit flag', function () {
    $patient = Patient::factory()->create();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900000,
        'paid_amount' => 0,
    ]);

    setFinancePolicy('finance.allow_deposit', false);

    expect(fn () => $invoice->recordPayment(100000, 'cash', isDeposit: true))
        ->toThrow(ValidationException::class, 'tiền cọc');

    setFinancePolicy('finance.allow_deposit', true);

    $payment = $invoice->recordPayment(100000, 'cash', isDeposit: true);

    expect($payment->is_deposit)->toBeTrue();
});

it('keeps patient payment summary must pay aligned to invoice total_amount', function () {
    $patient = Patient::factory()->create();

    Invoice::factory()->create([
        'patient_id' => $patient->id,
        'status' => Invoice::STATUS_ISSUED,
        'subtotal' => 1000000,
        'discount_amount' => 200000,
        'tax_amount' => 0,
        'total_amount' => 800000,
        'paid_amount' => 0,
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());
    $summary = $page->getPaymentSummaryProperty();

    expect((float) ($summary['must_pay_amount'] ?? 0))->toEqualWithDelta(800000.00, 0.01)
        ->and((float) ($summary['total_discount_amount'] ?? 0))->toEqualWithDelta(200000.00, 0.01);
});

function setFinancePolicy(string $key, bool $value): void
{
    ClinicSetting::setValue($key, $value, [
        'group' => 'finance',
        'label' => $key,
        'value_type' => 'boolean',
    ]);
}
