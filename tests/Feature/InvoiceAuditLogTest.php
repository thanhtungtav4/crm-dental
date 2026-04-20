<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Services\InvoiceWorkflowService;
use Illuminate\Support\Facades\Artisan;

it('records audit log when invoice is updated', function () {
    $invoice = Invoice::factory()->create([
        'discount_amount' => 0,
        'status' => 'issued',
    ]);
    $user = User::factory()->create([
        'branch_id' => $invoice->resolveBranchId(),
    ]);

    $this->actingAs($user);

    $invoice->update([
        'discount_amount' => 50000,
    ]);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_UPDATE)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->patient_id)->toBe($invoice->patient_id)
        ->and($log->branch_id)->toBe($invoice->resolveBranchId())
        ->and($log->metadata['patient_id'] ?? null)->toBe($invoice->patient_id);
});

it('records audit log when invoice is cancelled through workflow service', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'issued',
    ]);
    $user = User::factory()->create([
        'branch_id' => $invoice->resolveBranchId(),
    ]);
    $user->assignRole('Manager');

    $this->actingAs($user);

    app(InvoiceWorkflowService::class)->cancel($invoice, 'Cap nhat sai');

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->patient_id)->toBe($invoice->patient_id)
        ->and($log->branch_id)->toBe($invoice->resolveBranchId())
        ->and($log->metadata['previous_status'] ?? null)->toBe('issued')
        ->and($log->metadata['status_from'] ?? null)->toBe('issued')
        ->and($log->metadata['status_to'] ?? null)->toBe(Invoice::STATUS_CANCELLED)
        ->and($log->metadata['reason'] ?? null)->toBe('Cap nhat sai');
});

it('records audit log when invoice is cancelled through the canonical model boundary', function () {
    $invoice = Invoice::factory()->create([
        'status' => Invoice::STATUS_ISSUED,
    ]);
    $user = User::factory()->create([
        'branch_id' => $invoice->resolveBranchId(),
    ]);
    $user->assignRole('Manager');

    $this->actingAs($user);

    $invoice->cancel('Model boundary cancel', $user->id);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['reason'] ?? null)->toBe('Model boundary cancel')
        ->and($log->metadata['status_to'] ?? null)->toBe(Invoice::STATUS_CANCELLED);
});

it('records workflow audit when payment changes invoice status', function () {
    $patient = Patient::factory()->create();
    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'total_amount' => 1000000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
    ]);
    $user = User::factory()->create([
        'branch_id' => $invoice->resolveBranchId(),
    ]);

    $this->actingAs($user);

    $invoice->recordPayment(400000, 'cash', 'Thanh toán đợt 1');

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_UPDATE)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['status_from'] ?? null)->toBe(Invoice::STATUS_ISSUED)
        ->and($log->metadata['status_to'] ?? null)->toBe(Invoice::STATUS_PARTIAL)
        ->and($log->metadata['trigger'] ?? null)->toBe('payment_recorded')
        ->and($log->metadata['payment_id'] ?? null)->toBeInt();
});

it('records sync audit when overdue command changes invoice status', function () {
    $patient = Patient::factory()->create();
    $manager = User::factory()->create([
        'branch_id' => $patient->first_branch_id,
    ]);
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $invoice = Invoice::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $patient->first_branch_id,
        'total_amount' => 900000,
        'paid_amount' => 0,
        'status' => Invoice::STATUS_ISSUED,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    Artisan::call('invoices:sync-overdue-status');

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_SYNC)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($manager->id)
        ->and($log->metadata['status_from'] ?? null)->toBe(Invoice::STATUS_ISSUED)
        ->and($log->metadata['status_to'] ?? null)->toBe(Invoice::STATUS_OVERDUE)
        ->and($log->metadata['trigger'] ?? null)->toBe('automation_overdue_sync')
        ->and($log->metadata['command'] ?? null)->toBe('invoices:sync-overdue-status');
});
