<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\CareTicketWorkflowService;
use Spatie\Permission\Models\Permission;

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

    $this->actingAs($patient->ownerStaff);

    $workflow->transitionTicket(
        note: $ticket,
        toStatus: Note::CARE_STATUS_DONE,
        trigger: 'appointment_ticket_complete_test',
    );

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

it('records audit context when manually updating a care ticket through workflow service', function () {
    $workflow = app(CareTicketWorkflowService::class);
    $patient = makeCareWorkflowPatient();

    $ticket = Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $patient->customer_id,
        'user_id' => $patient->owner_staff_id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'general_care',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now()->addHour(),
        'content' => 'Theo dõi chăm sóc',
    ]);

    $this->actingAs($patient->ownerStaff);

    $workflow->updateManualTicket(
        note: $ticket,
        attributes: [
            'care_status' => Note::CARE_STATUS_DONE,
            'content' => 'Đã gọi lại và chốt lịch.',
        ],
        reason: 'Khach hang da xac nhan lich moi',
    );

    $auditLog = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CARE_TICKET)
        ->where('entity_id', $ticket->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->latest('id')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->actor_id)->toBe($patient->owner_staff_id)
        ->and($auditLog->metadata['trigger'] ?? null)->toBe('patient_notes_edit')
        ->and($auditLog->metadata['reason'] ?? null)->toBe('Khach hang da xac nhan lich moi')
        ->and($auditLog->metadata['care_status_to'] ?? null)->toBe(Note::CARE_STATUS_DONE);
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
    $customer = Customer::factory()->create();
    $owner = User::factory()->create([
        'branch_id' => $customer->branch_id,
    ]);
    $owner->assignRole('Manager');
    Permission::findOrCreate('Update:Note', 'web');
    $owner->givePermissionTo('Update:Note');

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
        'owner_staff_id' => $owner->id,
    ]);
}
