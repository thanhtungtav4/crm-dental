<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\ReceiptExpense;
use App\Models\User;
use App\Services\ReceiptExpenseWorkflowService;
use Illuminate\Validation\ValidationException;

it('records structured audit metadata when approving a receipt expense voucher', function (): void {
    [$receiptExpense, $manager] = makeReceiptExpenseWorkflowFixture();

    $this->actingAs($manager);

    $approvedVoucher = app(ReceiptExpenseWorkflowService::class)->approve(
        receiptExpense: $receiptExpense,
        reason: 'manager_reviewed',
    );

    expect($approvedVoucher->status)->toBe(ReceiptExpense::STATUS_APPROVED)
        ->and($approvedVoucher->posted_at)->toBeNull()
        ->and($approvedVoucher->posted_by)->toBeNull();

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_RECEIPT_EXPENSE)
        ->where('entity_id', $receiptExpense->id)
        ->where('action', AuditLog::ACTION_APPROVE)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($manager->id)
        ->and($auditLog?->branch_id)->toBe($receiptExpense->resolveBranchId())
        ->and($auditLog?->patient_id)->toBe($receiptExpense->patient_id)
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => ReceiptExpense::STATUS_DRAFT,
            'status_to' => ReceiptExpense::STATUS_APPROVED,
            'reason' => 'manager_reviewed',
            'trigger' => 'manual_approve',
            'receipt_expense_id' => $receiptExpense->id,
            'voucher_code' => $receiptExpense->voucher_code,
            'voucher_type' => $receiptExpense->voucher_type,
            'invoice_id' => $receiptExpense->invoice_id,
            'patient_id' => $receiptExpense->patient_id,
        ]);
});

it('records structured posting audit metadata and stamps the poster', function (): void {
    [$receiptExpense, $manager] = makeReceiptExpenseWorkflowFixture([
        'status' => ReceiptExpense::STATUS_APPROVED,
    ]);

    $this->actingAs($manager);

    $postedVoucher = app(ReceiptExpenseWorkflowService::class)->post(
        receiptExpense: $receiptExpense,
        reason: 'finance_posted',
    );

    expect($postedVoucher->status)->toBe(ReceiptExpense::STATUS_POSTED)
        ->and($postedVoucher->posted_at)->not->toBeNull()
        ->and($postedVoucher->posted_by)->toBe($manager->id);

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_RECEIPT_EXPENSE)
        ->where('entity_id', $receiptExpense->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog?->actor_id)->toBe($manager->id)
        ->and($auditLog?->branch_id)->toBe($receiptExpense->resolveBranchId())
        ->and($auditLog?->patient_id)->toBe($receiptExpense->patient_id)
        ->and($auditLog?->metadata)->toMatchArray([
            'status_from' => ReceiptExpense::STATUS_APPROVED,
            'status_to' => ReceiptExpense::STATUS_POSTED,
            'reason' => 'finance_posted',
            'trigger' => 'manual_post',
            'receipt_expense_id' => $receiptExpense->id,
            'voucher_code' => $receiptExpense->voucher_code,
            'voucher_type' => $receiptExpense->voucher_type,
            'invoice_id' => $receiptExpense->invoice_id,
            'patient_id' => $receiptExpense->patient_id,
            'posted_by' => $manager->id,
        ])
        ->and(data_get($auditLog, 'metadata.posted_at'))->not->toBeNull();
});

it('routes receipt expense transitions through the canonical model boundary', function (): void {
    [$receiptExpense, $manager] = makeReceiptExpenseWorkflowFixture();

    $this->actingAs($manager);

    $receiptExpense->approve('model_boundary_approve', $manager->id);
    $receiptExpense->refresh();

    expect($receiptExpense->status)->toBe(ReceiptExpense::STATUS_APPROVED);

    $receiptExpense->post('model_boundary_post', $manager->id);
    $receiptExpense->refresh();

    $postAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_RECEIPT_EXPENSE)
        ->where('entity_id', $receiptExpense->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($receiptExpense->status)->toBe(ReceiptExpense::STATUS_POSTED)
        ->and($receiptExpense->posted_by)->toBe($manager->id)
        ->and($postAudit)->not->toBeNull()
        ->and(data_get($postAudit, 'metadata.reason'))->toBe('model_boundary_post')
        ->and(data_get($postAudit, 'metadata.status_to'))->toBe(ReceiptExpense::STATUS_POSTED);
});

it('blocks raw status changes outside the workflow service', function (): void {
    [$receiptExpense, $manager] = makeReceiptExpenseWorkflowFixture();

    $this->actingAs($manager);

    expect(fn () => $receiptExpense->update([
        'status' => ReceiptExpense::STATUS_APPROVED,
    ]))->toThrow(ValidationException::class, 'Trang thai phieu thu chi chi duoc thay doi qua ReceiptExpenseWorkflowService.');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{0: ReceiptExpense, 1: User}
 */
function makeReceiptExpenseWorkflowFixture(array $overrides = []): array
{
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');
    $manager->givePermissionTo('Update:ReceiptExpense');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    auth()->login($manager);

    $invoice = Invoice::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'invoice_no' => 'INV-RX-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        'subtotal' => 850000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $receiptExpense = ReceiptExpense::query()->create(array_merge([
        'clinic_id' => $branch->id,
        'patient_id' => $patient->id,
        'invoice_id' => $invoice->id,
        'voucher_code' => 'PTT-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        'voucher_type' => 'receipt',
        'voucher_date' => now()->toDateString(),
        'amount' => 850000,
        'payment_method' => 'transfer',
        'payer_or_receiver' => $patient->full_name,
        'status' => ReceiptExpense::STATUS_DRAFT,
    ], $overrides));

    auth()->logout();

    return [$receiptExpense, $manager];
}
