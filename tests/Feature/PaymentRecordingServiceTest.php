<?php

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\PaymentRecordingService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('records payments through the canonical service with branch scoped receiver sanitization', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900_000,
    ]);

    $payment = app(PaymentRecordingService::class)->record(
        invoice: $invoice,
        data: [
            'amount' => 300_000,
            'method' => 'cash',
            'direction' => 'receipt',
            'payment_source' => 'patient',
            'paid_at' => now(),
            'received_by' => $manager->id,
        ],
        actor: $manager,
        fallbackNote: 'Thu theo hoa don',
    );

    expect($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->received_by)->toBe($manager->id)
        ->and($payment->note)->toBe('Thu theo hoa don');
});

it('returns the existing payment when the same transaction ref is retried through the canonical service', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 900_000,
    ]);

    $first = app(PaymentRecordingService::class)->record(
        invoice: $invoice,
        data: [
            'amount' => 200_000,
            'method' => 'transfer',
            'direction' => 'receipt',
            'payment_source' => 'patient',
            'paid_at' => now(),
            'transaction_ref' => 'FIN-DUP-001',
        ],
        actor: $manager,
    );

    $second = app(PaymentRecordingService::class)->record(
        invoice: $invoice,
        data: [
            'amount' => 200_000,
            'method' => 'transfer',
            'direction' => 'receipt',
            'payment_source' => 'patient',
            'paid_at' => now()->addSecond(),
            'transaction_ref' => 'FIN-DUP-001',
        ],
        actor: $manager,
    );

    expect($first->id)->toBe($second->id)
        ->and($invoice->payments()->count())->toBe(1);
});

it('rejects received_by outside the accessible branch scope in the canonical service', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $outsideUser = User::factory()->create(['branch_id' => $branchB->id]);
    $outsideUser->assignRole('Manager');

    $patient = Patient::factory()->create(['first_branch_id' => $branchA->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branchA->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 700_000,
    ]);

    $this->actingAs($manager);

    expect(fn () => app(PaymentRecordingService::class)->record(
        invoice: $invoice,
        data: [
            'amount' => 100_000,
            'method' => 'cash',
            'direction' => 'receipt',
            'payment_source' => 'patient',
            'paid_at' => now(),
            'received_by' => $outsideUser->id,
        ],
        actor: $manager,
    ))->toThrow(ValidationException::class, 'pham vi chi nhanh');
});

it('routes all payment creation surfaces through PaymentRecordingService', function (): void {
    $createPaymentPage = File::get(app_path('Filament/Resources/Payments/Pages/CreatePayment.php'));
    $invoiceRelationManager = File::get(app_path('Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php'));
    $invoicesTable = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));

    expect($createPaymentPage)
        ->toContain('PaymentRecordingService::class')
        ->not->toContain('->recordPayment(');

    expect($invoiceRelationManager)
        ->toContain('PaymentRecordingService::class')
        ->not->toContain('->recordPayment(');

    expect($invoicesTable)
        ->toContain('PaymentRecordingService::class')
        ->not->toContain('->recordPayment(');
});
