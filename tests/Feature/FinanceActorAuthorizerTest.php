<?php

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\FinanceActorAuthorizer;
use App\Services\PaymentRecordingService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('only exposes receivable actors within the accessible branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $receiverA = User::factory()->create(['branch_id' => $branchA->id]);
    $receiverA->assignRole('Manager');

    $receiverB = User::factory()->create(['branch_id' => $branchB->id]);
    $receiverB->assignRole('Manager');

    $options = app(FinanceActorAuthorizer::class)->assignableReceiverOptions($manager, $branchA->id);

    expect($options)->toHaveKey($manager->id)
        ->and($options)->toHaveKey($receiverA->id)
        ->and($options)->not->toHaveKey($receiverB->id);
});

it('rejects received_by outside accessible branch scope', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $outsideUser = User::factory()->create(['branch_id' => $branchB->id]);
    $outsideUser->assignRole('Manager');

    expect(fn () => app(FinanceActorAuthorizer::class)->sanitizeReceivedBy(
        actor: $manager,
        receivedBy: $outsideUser->id,
        branchId: $branchA->id,
    ))->toThrow(ValidationException::class, 'pham vi chi nhanh');
});

it('sanitizes received_by server side when creating a payment', function (): void {
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
        'total_amount' => 500_000,
    ]);

    $this->actingAs($manager);

    $page = app(CreatePayment::class);
    $creator = function (array $data) {
        return $this->handleRecordCreation($data);
    };
    $creator = $creator->bindTo($page, CreatePayment::class);

    expect(fn () => $creator([
        'invoice_id' => $invoice->id,
        'amount' => 200_000,
        'method' => 'cash',
        'direction' => 'receipt',
        'payment_source' => 'patient',
        'paid_at' => now(),
        'received_by' => $outsideUser->id,
    ]))->toThrow(ValidationException::class, 'pham vi chi nhanh');
});

it('routes payment receiver selection through FinanceActorAuthorizer surfaces', function (): void {
    $paymentForm = File::get(app_path('Filament/Resources/Payments/Schemas/PaymentForm.php'));
    $createPaymentPage = File::get(app_path('Filament/Resources/Payments/Pages/CreatePayment.php'));
    $invoiceRelationManager = File::get(app_path('Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php'));
    $paymentsTable = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));
    $recordingService = File::get(app_path('Services/PaymentRecordingService.php'));

    expect($paymentForm)->toContain('FinanceActorAuthorizer::class')
        ->and($invoiceRelationManager)->toContain('FinanceActorAuthorizer::class')
        ->and($paymentsTable)->toContain('FinanceActorAuthorizer::class')
        ->and($recordingService)->toContain('FinanceActorAuthorizer::class')
        ->and($createPaymentPage)->toContain(PaymentRecordingService::class);
});

it('ignores hidden invoice query params on the create payment page', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');

    $hiddenPatient = Patient::factory()->create(['first_branch_id' => $branchB->id]);
    $hiddenInvoice = Invoice::factory()->create([
        'patient_id' => $hiddenPatient->id,
        'branch_id' => $branchB->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 400_000,
    ]);

    $component = Livewire::actingAs($manager)
        ->withQueryParams([
            'invoice_id' => $hiddenInvoice->id,
        ])
        ->test(CreatePayment::class);

    expect($component->get('data.invoice_id'))->toBeNull();
});
