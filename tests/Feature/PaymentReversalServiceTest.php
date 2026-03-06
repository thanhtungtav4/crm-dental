<?php

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentReversalService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('creates a single reversal through the canonical service and marks the original payment reversed', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1_000_000,
    ]);

    $receipt = $invoice->recordPayment(
        amount: 400_000,
        method: 'cash',
        notes: 'Thanh toán gốc',
        receivedBy: $manager->id,
    );

    $refund = app(PaymentReversalService::class)->reverse(
        payment: $receipt,
        amount: 200_000,
        paidAt: now(),
        refundReason: 'Dieu chinh dich vu',
        note: 'Hoan tien mot phan',
        actorId: $manager->id,
    );

    expect($receipt->fresh()->isReversed())->toBeTrue()
        ->and($refund->reversal_of_id)->toBe($receipt->id)
        ->and($refund->direction)->toBe('refund')
        ->and($refund->transaction_ref)->toBe(Payment::reversalTransactionRef($invoice->id, $receipt->id))
        ->and($invoice->fresh()->payments()->where('reversal_of_id', $receipt->id)->count())->toBe(1);
});

it('returns the existing reversal on retry instead of creating a duplicate refund', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 800_000,
    ]);

    $receipt = $invoice->recordPayment(
        amount: 300_000,
        method: 'cash',
        notes: 'Thanh toán gốc',
        receivedBy: $manager->id,
    );

    $first = app(PaymentReversalService::class)->reverse(
        payment: $receipt,
        amount: 150_000,
        paidAt: now(),
        refundReason: 'Retry-safe refund',
        note: 'Lần 1',
        actorId: $manager->id,
    );

    $second = app(PaymentReversalService::class)->reverse(
        payment: $receipt->fresh(),
        amount: 150_000,
        paidAt: now()->addSecond(),
        refundReason: 'Retry-safe refund',
        note: 'Lần 2',
        actorId: $manager->id,
    );

    expect($first->id)->toBe($second->id)
        ->and(Payment::query()->where('reversal_of_id', $receipt->id)->count())->toBe(1);
});

it('rejects reversal amounts that exceed the original receipt', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 600_000,
    ]);

    $receipt = $invoice->recordPayment(
        amount: 200_000,
        method: 'cash',
        notes: 'Thanh toán gốc',
        receivedBy: $manager->id,
    );

    expect(fn () => app(PaymentReversalService::class)->reverse(
        payment: $receipt,
        amount: 250_000,
        paidAt: now(),
        refundReason: 'Vuot muc',
        note: 'Vuot muc',
        actorId: $manager->id,
    ))->toThrow(ValidationException::class, 'không được vượt quá');
});

it('keeps reversal creation idempotent under repeated concurrent submissions', function (): void {
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

    $receipt = $invoice->recordPayment(
        amount: 350_000,
        method: 'cash',
        notes: 'Thanh toán gốc',
        receivedBy: $manager->id,
    );

    $receiptId = (int) $receipt->id;
    $managerId = (int) $manager->id;

    $tasks = [];
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $tasks[] = static function () use ($managerId, $receiptId): int {
            auth()->loginUsingId($managerId);

            return app(PaymentReversalService::class)->reverse(
                payment: Payment::query()->findOrFail($receiptId),
                amount: 100_000,
                paidAt: now(),
                refundReason: 'Stress refund',
                note: 'Stress refund',
                actorId: $managerId,
            )->id;
        };
    }

    $results = Concurrency::driver('sync')->run($tasks);

    expect(Payment::query()->where('reversal_of_id', $receiptId)->count())->toBe(1)
        ->and(collect($results)->filter()->unique()->count())->toBe(1);
});

it('routes all refund surfaces through PaymentReversalService', function (): void {
    $paymentsTable = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));
    $invoiceRelationManager = File::get(app_path('Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php'));
    $patientRelationManager = File::get(app_path('Filament/Resources/Patients/RelationManagers/PatientPaymentsRelationManager.php'));

    expect($paymentsTable)
        ->toContain('PaymentReversalService::class')
        ->not->toContain('markReversed(');

    expect($invoiceRelationManager)
        ->toContain('PaymentReversalService::class')
        ->not->toContain('markReversed(');

    expect($patientRelationManager)
        ->toContain('PaymentReversalService::class')
        ->not->toContain('markReversed(');
});
