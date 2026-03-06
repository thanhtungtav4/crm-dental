<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('marks canonical care tickets as workflow managed and blocks update/delete in policy', function () {
    $branch = Branch::factory()->create(['active' => true]);

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
    ]);

    $canonicalTicket = Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $manager->id,
        'branch_id' => $branch->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'appointment_reminder',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now(),
        'content' => 'Ticket canonical',
        'source_type' => App\Models\Appointment::class,
        'source_id' => 1001,
        'ticket_key' => Note::ticketKey(App\Models\Appointment::class, 1001, 'appointment_reminder'),
    ]);

    $manualNote = Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $manager->id,
        'branch_id' => $branch->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'other',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'care_at' => now(),
        'content' => 'Note thu cong',
        'source_type' => 'patient_care',
        'source_id' => $patient->id,
    ]);

    expect($canonicalTicket->isWorkflowManagedCareTicket())->toBeTrue()
        ->and($manualNote->isWorkflowManagedCareTicket())->toBeFalse()
        ->and($manager->can('update', $canonicalTicket))->toBeFalse()
        ->and($manager->can('delete', $canonicalTicket))->toBeFalse()
        ->and($manager->can('update', $manualNote))->toBeTrue()
        ->and($manager->can('delete', $manualNote))->toBeTrue();
});

it('removes direct create edit and destructive note resource surfaces', function () {
    $resource = File::get(app_path('Filament/Resources/Notes/NoteResource.php'));
    $listPage = File::get(app_path('Filament/Resources/Notes/Pages/ListNotes.php'));
    $table = File::get(app_path('Filament/Resources/Notes/Tables/NotesTable.php'));
    $relationManager = File::get(app_path('Filament/Resources/Patients/Relations/PatientNotesRelationManager.php'));

    expect($resource)
        ->not->toContain("CreateNote::route('/create')")
        ->not->toContain("EditNote::route('/{record}/edit')")
        ->and($listPage)->not->toContain('CreateAction::make()')
        ->and($table)->not->toContain('EditAction::make()')
        ->and($table)->not->toContain('DeleteBulkAction::make()')
        ->and($relationManager)
        ->toContain('isWorkflowManagedCareTicket()')
        ->toContain('canMutateCareRecord(');
});
