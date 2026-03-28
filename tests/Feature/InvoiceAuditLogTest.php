<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceWorkflowService;

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
