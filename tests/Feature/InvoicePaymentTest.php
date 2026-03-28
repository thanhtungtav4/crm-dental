<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\InvoiceWorkflowService;
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
    $manager = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

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
        'status' => 'issued',
        'due_date' => now()->subDays(3)->toDateString(),
    ]);
    app(InvoiceWorkflowService::class)->cancel($cancelledInvoice, 'Cap nhat nham');

    Artisan::call('invoices:sync-overdue-status');

    expect($overdueInvoice->fresh()->status)->toBe('overdue')
        ->and($cancelledInvoice->fresh()->status)->toBe('cancelled');
});

it('allows automation actor without branch assignments to sync overdue invoices', function () {
    $patient = Patient::factory()->create();
    $automationActor = User::factory()->create([
        'email' => 'automation.invoice.'.fake()->unique()->safeEmail(),
        'branch_id' => null,
    ]);
    $automationActor->assignRole('AutomationService');
    seedInvoiceAutomationActorSetting('scheduler.automation_actor_user_id', $automationActor->id, 'integer');
    seedInvoiceAutomationActorSetting('scheduler.automation_actor_required_role', 'AutomationService');

    $invoice = Invoice::query()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'invoice_no' => 'INV-AUTO-'.strtoupper(fake()->unique()->bothify('####??')),
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($automationActor);

    $this->artisan('invoices:sync-overdue-status')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('overdue');
});

it('scopes overdue sync command to the acting manager branch', function () {
    $managerBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $managerBranchPatient = Patient::factory()->create([
        'first_branch_id' => $managerBranch->id,
    ]);
    $otherBranchPatient = Patient::factory()->create([
        'first_branch_id' => $otherBranch->id,
    ]);
    $manager = User::factory()->create([
        'branch_id' => $managerBranch->id,
    ]);

    $visibleInvoice = Invoice::query()->create([
        'patient_id' => $managerBranchPatient->id,
        'branch_id' => $managerBranchPatient->first_branch_id,
        'invoice_no' => 'INV-MGR-'.strtoupper(fake()->unique()->bothify('####??')),
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $hiddenInvoice = Invoice::query()->create([
        'patient_id' => $otherBranchPatient->id,
        'branch_id' => $otherBranchPatient->first_branch_id,
        'invoice_no' => 'INV-OTH-'.strtoupper(fake()->unique()->bothify('####??')),
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => 'issued',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $this->artisan('invoices:sync-overdue-status')
        ->assertSuccessful();

    expect($visibleInvoice->fresh()->status)->toBe('overdue')
        ->and($hiddenInvoice->fresh()->status)->toBe('issued');
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

function seedInvoiceAutomationActorSetting(string $key, mixed $value, string $type = 'text'): void
{
    ClinicSetting::setValue($key, $value, [
        'group' => 'scheduler',
        'label' => $key,
        'value_type' => $type,
        'is_secret' => false,
        'is_active' => true,
        'sort_order' => 900,
    ]);
}
