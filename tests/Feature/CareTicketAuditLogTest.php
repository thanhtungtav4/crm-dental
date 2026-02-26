<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;

it('records audit log when care ticket is completed', function () {
    $note = makeCareTicketForAudit();
    $user = User::factory()->create([
        'branch_id' => $note->patient?->first_branch_id,
    ]);

    $this->actingAs($user);

    $note->update([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CARE_TICKET)
        ->where('entity_id', $note->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->metadata['patient_id'] ?? null)->toBe($note->patient_id)
        ->and($log->metadata['care_status_to'] ?? null)->toBe(Note::CARE_STATUS_DONE);
});

function makeCareTicketForAudit(array $overrides = []): Note
{
    $branch = Branch::factory()->create();

    $staff = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    return Note::create(array_merge([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $staff->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'general_care',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'content' => 'Theo dõi chăm sóc',
        'care_at' => now()->addHour(),
    ], $overrides));
}
