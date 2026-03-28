<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\CareTicketWorkflowService;
use Spatie\Permission\Models\Permission;

it('records audit log when care ticket is completed', function () {
    $note = makeCareTicketForAudit();
    $user = User::factory()->create([
        'branch_id' => $note->patient?->first_branch_id,
    ]);
    $user->assignRole('Manager');
    Permission::findOrCreate('Update:Note', 'web');
    $user->givePermissionTo('Update:Note');

    $this->actingAs($user);

    app(CareTicketWorkflowService::class)->updateManualTicket($note, [
        'care_status' => Note::CARE_STATUS_DONE,
    ], reason: 'Đã xử lý xong qua điện thoại');

    $log = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_CARE_TICKET)
        ->where('entity_id', $note->id)
        ->where('action', AuditLog::ACTION_COMPLETE)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->patient_id)->toBe($note->patient_id)
        ->and($log->branch_id)->toBe($note->resolveBranchId())
        ->and($log->metadata['patient_id'] ?? null)->toBe($note->patient_id)
        ->and($log->metadata['care_status_to'] ?? null)->toBe(Note::CARE_STATUS_DONE)
        ->and($log->metadata['trigger'] ?? null)->toBe('patient_notes_edit')
        ->and($log->metadata['reason'] ?? null)->toBe('Đã xử lý xong qua điện thoại');
});

function makeCareTicketForAudit(array $overrides = []): Note
{
    $branch = Branch::factory()->create();

    $staff = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $staff->assignRole('Manager');
    Permission::findOrCreate('Update:Note', 'web');
    $staff->givePermissionTo('Update:Note');

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
