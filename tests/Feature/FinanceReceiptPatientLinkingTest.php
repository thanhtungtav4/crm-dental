<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('keeps patient link visible by default on invoice list', function (): void {
    $invoicesTable = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));

    expect($invoicesTable)
        ->toContain("TextColumn::make('patient.full_name')")
        ->toContain("PatientResource::getUrl('view'")
        ->toContain('->toggleable()')
        ->toContain('Mã BN:');
});

it('stores patient and invoice references on receipts expense vouchers', function (): void {
    expect(Schema::hasColumns('receipts_expense', ['patient_id', 'invoice_id']))->toBeTrue();
});

it('supports patient and invoice linking in receipts expense form and table', function (): void {
    $form = File::get(app_path('Filament/Resources/ReceiptsExpense/Schemas/ReceiptsExpenseForm.php'));
    $table = File::get(app_path('Filament/Resources/ReceiptsExpense/Tables/ReceiptsExpenseTable.php'));
    $model = File::get(app_path('Models/ReceiptExpense.php'));

    expect($form)
        ->toContain("Select::make('patient_id')")
        ->toContain("Select::make('invoice_id')")
        ->toContain('invoiceOptionsForPatient')
        ->toContain('defaultVoucherType')
        ->toContain('defaultPaymentMethod')
        ->toContain('defaultAmount');

    expect($table)
        ->toContain("TextColumn::make('patient.full_name')")
        ->toContain("TextColumn::make('invoice.invoice_no')")
        ->toContain("Action::make('openPatient')")
        ->toContain("Action::make('openInvoice')");

    expect($model)
        ->toContain("'patient_id'")
        ->toContain("'invoice_id'")
        ->toContain('public function patient(): BelongsTo')
        ->toContain('public function invoice(): BelongsTo');
});

it('provides finance quick actions from invoices and payments to create receipt vouchers', function (): void {
    $invoicesTable = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));
    $paymentsTable = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));
    $patientPaymentsRelationManager = File::get(app_path('Filament/Resources/Patients/RelationManagers/PatientPaymentsRelationManager.php'));

    expect($invoicesTable)
        ->toContain("Action::make('create_receipt_expense_voucher')")
        ->toContain("ReceiptsExpenseResource::getUrl('create'");

    expect($paymentsTable)
        ->toContain("Action::make('create_receipt_expense_voucher')")
        ->toContain("ReceiptsExpenseResource::getUrl('create'");

    expect($patientPaymentsRelationManager)
        ->toContain("TextColumn::make('invoice.invoice_no')")
        ->toContain("Action::make('openInvoice')")
        ->toContain("InvoiceResource::getUrl('edit'");
});

it('renders receipts expense list and create screens after linking update', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.receipts-expense.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.receipts-expense.create'))
        ->assertSuccessful()
        ->assertSee('Bệnh nhân')
        ->assertSee('Hóa đơn liên quan');
});
