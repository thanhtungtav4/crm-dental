<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('normalizes legacy care statuses and keeps query aliases compatible', function () {
    $scheduledCare = makeCareNoteRecord([
        'care_status' => 'planned',
    ]);

    $followUpCare = makeCareNoteRecord([
        'care_status' => 'no_response',
    ]);

    $aliases = Note::statusesForQuery([Note::CARE_STATUS_NOT_STARTED]);

    expect($scheduledCare->care_status)->toBe(Note::CARE_STATUS_NOT_STARTED)
        ->and($followUpCare->care_status)->toBe(Note::CARE_STATUS_NEED_FOLLOWUP)
        ->and($aliases)->toContain('not_started')
        ->and($aliases)->toContain('planned')
        ->and($aliases)->toContain('PLANNED');
});

it('blocks invalid care ticket status transition with CARE_TICKET_STATE_INVALID', function () {
    $careTicket = makeCareNoteRecord([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    expect(fn () => $careTicket->update([
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
    ]))->toThrow(ValidationException::class, 'CARE_TICKET_STATE_INVALID');
});

it('allows valid care ticket transitions and locks after done', function () {
    $careTicket = makeCareNoteRecord([
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
    ]);

    $careTicket->update([
        'care_status' => Note::CARE_STATUS_IN_PROGRESS,
    ]);

    $careTicket->update([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    expect($careTicket->fresh()->care_status)->toBe(Note::CARE_STATUS_DONE)
        ->and(Note::careStatusLabel($careTicket->care_status))->toBe('Hoàn thành');

    expect(fn () => $careTicket->update([
        'care_status' => Note::CARE_STATUS_NEED_FOLLOWUP,
    ]))->toThrow(ValidationException::class, 'CARE_TICKET_STATE_INVALID');
});

function makeCareNoteRecord(array $overrides = []): Note
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
