<?php

use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\InstallmentPlan;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

it('removes cancelled from invoice form status options', function (): void {
    expect(Invoice::formStatusOptions())->not->toHaveKey(Invoice::STATUS_CANCELLED);
});

it('blocks forged create payload from creating a cancelled invoice', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $page = app(CreateInvoice::class);
    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeCreate($data);
    };
    $mutator = $mutator->bindTo($page, CreateInvoice::class);

    expect(fn () => $mutator([
        'branch_id' => $branch->id,
        'subtotal' => 500000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 500000,
        'status' => Invoice::STATUS_CANCELLED,
    ]))->toThrow(ValidationException::class, 'Khong the tao hoa don');
});

it('blocks direct invoice cancellation outside the workflow service', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    expect(fn () => $invoice->update(['status' => Invoice::STATUS_CANCELLED]))
        ->toThrow(ValidationException::class, 'InvoiceWorkflowService');
});

it('blocks forged edit payload from changing invoice status directly', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $page = app(EditInvoice::class);
    $page->record = $invoice;
    $mutator = function (array $data): array {
        return $this->mutateFormDataBeforeSave($data);
    };
    $mutator = $mutator->bindTo($page, EditInvoice::class);

    expect(fn () => $mutator([
        'status' => Invoice::STATUS_CANCELLED,
        'subtotal' => $invoice->subtotal,
        'discount_amount' => $invoice->discount_amount,
        'tax_amount' => $invoice->tax_amount,
        'total_amount' => $invoice->total_amount,
    ]))->toThrow(ValidationException::class, 'InvoiceWorkflowService');
});

it('cancels invoice through workflow service with audit trail', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $cancelledInvoice = app(InvoiceWorkflowService::class)->cancel($invoice, 'Tao nham hoa don');

    expect($cancelledInvoice->status)->toBe(Invoice::STATUS_CANCELLED)
        ->and($cancelledInvoice->canBeCancelled())->toBeFalse();

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->metadata['previous_status'] ?? null)->toBe(Invoice::STATUS_ISSUED)
        ->and($log->metadata['status_from'] ?? null)->toBe(Invoice::STATUS_ISSUED)
        ->and($log->metadata['status_to'] ?? null)->toBe(Invoice::STATUS_CANCELLED)
        ->and($log->metadata['reason'] ?? null)->toBe('Tao nham hoa don');
});

it('cancels invoice through the canonical model boundary', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    $invoice->cancel('model_boundary_cancel', $manager->id);

    $cancelAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_CANCELLED)
        ->and($cancelAudit)->not->toBeNull()
        ->and(data_get($cancelAudit, 'metadata.reason'))->toBe('model_boundary_cancel')
        ->and(data_get($cancelAudit, 'metadata.status_to'))->toBe(Invoice::STATUS_CANCELLED);
});

it('blocks invoice cancellation once payments exist', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1000000,
    ]);

    $invoice->recordPayment(150000, 'cash');

    expect(fn () => app(InvoiceWorkflowService::class)->cancel($invoice))
        ->toThrow(ValidationException::class, 'da co thanh toan');
});

it('blocks invoice cancellation when installment plan exists', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1200000,
    ]);

    InstallmentPlan::query()->create([
        'invoice_id' => $invoice->id,
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'financed_amount' => 1200000,
        'remaining_amount' => 1200000,
        'number_of_installments' => 6,
        'installment_amount' => 200000,
        'start_date' => now()->toDateString(),
    ]);

    expect(fn () => app(InvoiceWorkflowService::class)->cancel($invoice))
        ->toThrow(ValidationException::class, 'ho so tra gop');
});

it('blocks new payments on cancelled invoices', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
    ]);

    app(InvoiceWorkflowService::class)->cancel($invoice);

    expect(fn () => $invoice->fresh()->recordPayment(100000, 'cash'))
        ->toThrow(ValidationException::class, 'hoa don da huy');
});

it('routes invoice cancellation through dedicated workflow surfaces', function (): void {
    $editPage = File::get(app_path('Filament/Resources/Invoices/Pages/EditInvoice.php'));
    $table = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));

    expect($editPage)
        ->toContain('mutateFormDataBeforeSave')
        ->toContain("Action::make('cancel_invoice')")
        ->toContain('InvoiceWorkflowService::class');

    expect($table)
        ->toContain("Action::make('cancel')")
        ->toContain('InvoiceWorkflowService::class');
});

it('routes overdue invoice sync through workflow service', function (): void {
    $command = File::get(app_path('Console/Commands/SyncInvoiceOverdueStatus.php'));

    expect($command)
        ->toContain('InvoiceWorkflowService::class')
        ->toContain('syncFinancialStatus');
});

it('returns invoices from financial status model boundaries', function (): void {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $patient = Patient::factory()->create(['first_branch_id' => $branch->id]);

    $previewInvoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 500000,
        'paid_amount' => 0,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $previewTransitionedInvoice = $previewInvoice->updatePaymentStatus();

    expect($previewTransitionedInvoice)->toBe($previewInvoice)
        ->and($previewTransitionedInvoice->status)->toBe(Invoice::STATUS_OVERDUE)
        ->and($previewTransitionedInvoice->paid_at)->toBeNull();

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'status' => Invoice::STATUS_ISSUED,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    Payment::query()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'amount' => 300000,
        'direction' => 'receipt',
        'method' => 'cash',
        'paid_at' => now(),
        'received_by' => $manager->id,
        'note' => 'Thanh toán đợt 1',
    ]);

    $syncedInvoice = $invoice->fresh()->updatePaidAmount(
        actorId: $manager->id,
        reason: 'return_contract_check',
        metadata: ['trigger' => 'test'],
    );

    expect($syncedInvoice)->toBeInstanceOf(Invoice::class)
        ->and($syncedInvoice->is($invoice))->toBeTrue()
        ->and((float) $syncedInvoice->paid_amount)->toEqualWithDelta(300000.00, 0.01)
        ->and($syncedInvoice->status)->toBe(Invoice::STATUS_PARTIAL);
});
