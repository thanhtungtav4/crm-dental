<?php

use App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;

it('includes appointment and care audit logs in patient activity timeline', function () {
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

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

    $appointment = Appointment::create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $note = Note::create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'user_id' => $doctor->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'appointment_reminder',
        'care_channel' => 'call',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_mode' => 'scheduled',
        'content' => 'Theo dõi chăm sóc',
        'care_at' => now()->addHour(),
    ]);

    $actor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($actor);

    $appointment->update([
        'status' => Appointment::STATUS_RESCHEDULED,
        'reschedule_reason' => 'Dời lịch theo yêu cầu',
    ]);

    $note->update([
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    $widget = app(PatientActivityTimelineWidget::class);
    $widget->record = $patient;

    $activities = $widget->getActivities();

    expect($activities->where('type', 'audit'))->not->toBeEmpty()
        ->and($activities->pluck('title')->all())
        ->toContain('Hẹn lại lịch', 'Hoàn thành chăm sóc');
});
