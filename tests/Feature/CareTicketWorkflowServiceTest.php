<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\CareTicketWorkflowService;

it('upserts care tickets idempotently by canonical ticket key', function () {
    $workflow = app(CareTicketWorkflowService::class);
    $patient = makeCareWorkflowPatient();
    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $payload = [
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'no_show_recovery',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'is_recurring' => false,
        'care_at' => now(),
        'content' => 'Nhắc recovery',
    ];

    $firstTicket = $workflow->upsertSourceTicket($payload, Appointment::class, $appointment->id);
    $secondTicket = $workflow->upsertSourceTicket(array_merge($payload, [
        'content' => 'Nhắc recovery cap nhat',
    ]), Appointment::class, $appointment->id);

    expect($firstTicket->id)->toBe($secondTicket->id)
        ->and(Note::query()
            ->where('source_type', Appointment::class)
            ->where('source_id', $appointment->id)
            ->where('care_type', 'no_show_recovery')
            ->count())->toBe(1)
        ->and($secondTicket->ticket_key)->toBe(Note::ticketKey(
            Appointment::class,
            $appointment->id,
            'no_show_recovery',
        ))
        ->and($secondTicket->content)->toBe('Nhắc recovery cap nhat');
});

it('preserves done care tickets when workflow re-syncs the same source', function () {
    $workflow = app(CareTicketWorkflowService::class);
    $patient = makeCareWorkflowPatient();
    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $ticket = $workflow->upsertSourceTicket([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'appointment_reminder',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now(),
        'content' => 'Nhắc lịch hẹn',
    ], Appointment::class, $appointment->id);

    $ticket->update([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    $updatedTicket = $workflow->upsertSourceTicket([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'appointment_reminder',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now()->addDay(),
        'content' => 'Nhắc lịch hẹn da doi',
    ], Appointment::class, $appointment->id);

    expect($updatedTicket->id)->toBe($ticket->id)
        ->and($updatedTicket->care_status)->toBe(Note::CARE_STATUS_DONE)
        ->and($updatedTicket->content)->toBe('Nhắc lịch hẹn da doi');
});

it('supports recurring yearly ticket scopes without duplicating the same year', function () {
    $workflow = app(CareTicketWorkflowService::class);
    $patient = makeCareWorkflowPatient();

    $firstRun = $workflow->upsertSourceTicketWithState([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'birthday_care',
        'care_channel' => 'message',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'is_recurring' => true,
        'care_at' => now()->startOfDay(),
        'content' => 'Sinh nhat 2026',
    ], Patient::class, $patient->id, '2026');

    $secondRun = $workflow->upsertSourceTicketWithState([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'birthday_care',
        'care_channel' => 'message',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'is_recurring' => true,
        'care_at' => now()->startOfDay(),
        'content' => 'Sinh nhat 2026 lan 2',
    ], Patient::class, $patient->id, '2026');

    $thirdRun = $workflow->upsertSourceTicketWithState([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'birthday_care',
        'care_channel' => 'message',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'is_recurring' => true,
        'care_at' => now()->addYear()->startOfDay(),
        'content' => 'Sinh nhat 2027',
    ], Patient::class, $patient->id, '2027');

    expect($firstRun['was_created'])->toBeTrue()
        ->and($secondRun['was_created'])->toBeFalse()
        ->and($thirdRun['was_created'])->toBeTrue()
        ->and(Note::query()
            ->where('source_type', Patient::class)
            ->where('source_id', $patient->id)
            ->where('care_type', 'birthday_care')
            ->count())->toBe(2);
});

function makeCareWorkflowPatient(): Patient
{
    $owner = User::factory()->create();
    $customer = Customer::factory()->create();

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
        'owner_staff_id' => $owner->id,
    ]);
}
