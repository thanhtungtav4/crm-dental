<?php

use App\Filament\Pages\CustomerCare;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentSchedulingService;
use App\Services\CareTicketService;
use Livewire\Livewire;

it('uses note tickets as canonical source for appointment reminder tab', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $customer = Customer::factory()->create();
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
    ]);

    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $patient->first_branch_id,
        'status' => Appointment::STATUS_SCHEDULED,
        'date' => now()->subHour(),
    ]);

    $component = Livewire::test(CustomerCare::class)
        ->set('activeTab', 'appointment_reminder');

    $query = resolveCustomerCareQuery($component->instance());

    expect($query->getModel())->toBeInstanceOf(Note::class)
        ->and($query->count())->toBe(0);

    app(AppointmentSchedulingService::class)->transitionStatus(
        $appointment->fresh(),
        Appointment::STATUS_NO_SHOW,
        [
            'reason' => 'Patient did not arrive',
        ],
    );

    app(CareTicketService::class)->syncAppointment($appointment->fresh());

    $query = resolveCustomerCareQuery($component->instance());

    expect($query->count())->toBe(1)
        ->and($query->first()->source_type)->toBe(Appointment::class)
        ->and($query->first()->source_id)->toBe($appointment->id);
});

it('uses note tickets as canonical source for birthday tab', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $customer = Customer::factory()->create();
    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $customer->branch_id,
        'birthday' => now()->toDateString(),
    ]);

    $component = Livewire::test(CustomerCare::class)
        ->set('activeTab', 'birthday');

    $query = resolveCustomerCareQuery($component->instance());

    expect($query->getModel())->toBeInstanceOf(Note::class)
        ->and($query->count())->toBe(0);

    $this->artisan('care:generate-birthday-tickets', [
        '--date' => now()->toDateString(),
    ])->assertSuccessful();

    $query = resolveCustomerCareQuery($component->instance());

    expect($query->count())->toBe(1)
        ->and($query->first()->source_type)->toBe(Patient::class)
        ->and($query->first()->source_id)->toBe($patient->id)
        ->and($query->first()->care_type)->toBe('birthday_care');
});

function resolveCustomerCareQuery(CustomerCare $page): \Illuminate\Database\Eloquent\Builder
{
    $method = new ReflectionMethod($page, 'getTableQuery');
    $method->setAccessible(true);

    return $method->invoke($page);
}
