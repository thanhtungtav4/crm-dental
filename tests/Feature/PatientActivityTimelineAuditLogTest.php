<?php

use App\Filament\Resources\Patients\Widgets\PatientActivityTimelineWidget;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FactoryOrder;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentSchedulingService;
use App\Services\CareTicketWorkflowService;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;

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
    $actor->assignRole('Manager');
    Permission::findOrCreate('Update:Note', 'web');
    $actor->givePermissionTo('Update:Note');

    $this->actingAs($actor);

    app(AppointmentSchedulingService::class)->reschedule(
        appointment: $appointment,
        startAt: $appointment->date?->copy()->addHour() ?? now()->addHour(),
        reason: 'Dời lịch theo yêu cầu',
    );

    app(CareTicketWorkflowService::class)->updateManualTicket($note, [
        'care_status' => Note::CARE_STATUS_DONE,
    ]);

    AuditLog::factory()->create([
        'entity_type' => AuditLog::ENTITY_FACTORY_ORDER,
        'entity_id' => 9001,
        'action' => AuditLog::ACTION_COMPLETE,
        'actor_id' => $actor->id,
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'metadata' => [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'factory_order_id' => 9001,
            'order_no' => 'LABO-9001',
            'status_to' => FactoryOrder::STATUS_DELIVERED,
            'supplier_name' => 'Labo Widget',
        ],
        'occurred_at' => now()->addMinutes(10),
    ]);

    $widget = app(PatientActivityTimelineWidget::class);
    $widget->record = $patient;

    $activities = $widget->getActivities();

    expect($activities->where('type', 'audit'))->not->toBeEmpty()
        ->and($activities->pluck('title')->all())
        ->toContain('Hẹn lại lịch', 'Hoàn thành chăm sóc', 'Hoàn thành labo');

    $getViewDataMethod = new ReflectionMethod($widget, 'getViewData');
    $getViewDataMethod->setAccessible(true);
    $viewData = $getViewDataMethod->invoke($widget);
    $blade = File::get(resource_path('views/filament/resources/patients/widgets/patient-activity-timeline-widget.blade.php'));

    expect($blade)
        ->not->toContain('@php')
        ->not->toContain('$this->getActivities()')
        ->and($viewData)->toHaveKeys(['activities', 'activityCount', 'showsMaxActivitiesFooter'])
        ->and($viewData['activityCount'])->toBe($activities->count())
        ->and($viewData['activities'][0])->toHaveKeys([
            'type_class',
            'type_label',
            'description_excerpt',
            'date_iso',
            'date_label',
            'time_label',
            'human_label',
        ]);
});
