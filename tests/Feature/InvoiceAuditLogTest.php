<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;

it('records audit log when invoice is updated', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create([
        'discount_amount' => 0,
        'status' => 'issued',
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
        ->and($log->metadata['patient_id'] ?? null)->toBe($invoice->patient_id);
});

it('records audit log when invoice is cancelled', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create([
        'status' => 'issued',
    ]);

    $this->actingAs($user);

    $invoice->update([
        'status' => 'cancelled',
    ]);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_INVOICE)
        ->where('entity_id', $invoice->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['previous_status'] ?? null)->toBe('issued');
});
