<?php

use App\Filament\Widgets\OperationalStatsWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Services\OperationalStatsReadModelService;
use Carbon\Carbon;

it('summarizes operational widget stats through a shared read model with branch scope', function (): void {
    $now = Carbon::parse('2026-03-29 10:30:00');
    Carbon::setTestNow($now);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $manager->assignRole('Manager');

    $doctorA = User::factory()->create([
        'branch_id' => $branchA->id,
    ]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create([
        'branch_id' => $branchB->id,
    ]);
    $doctorB->assignRole('Doctor');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $customerA = Customer::factory()->create([
        'branch_id' => $branchA->id,
        'created_at' => $now->copy()->subHour(),
        'updated_at' => $now->copy()->subHour(),
    ]);
    $customerB = Customer::factory()->create([
        'branch_id' => $branchB->id,
        'created_at' => $now->copy()->subMinutes(30),
        'updated_at' => $now->copy()->subMinutes(30),
    ]);
    Customer::factory()->create([
        'branch_id' => $branchA->id,
        'created_at' => $now->copy()->subDay(),
        'updated_at' => $now->copy()->subDay(),
    ]);

    $patientA = Patient::query()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
        'full_name' => $customerA->full_name,
        'phone' => $customerA->phone,
        'email' => $customerA->email,
    ]);
    $patientB = Patient::query()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
        'full_name' => $customerB->full_name,
        'phone' => $customerB->phone,
        'email' => $customerB->email,
    ]);

    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'date' => $now->copy()->setTime(14, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerB->id,
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'date' => $now->copy()->setTime(16, 0),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'date' => $now->copy()->addDay()->setTime(9, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerB->id,
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'date' => $now->copy()->addDay()->setTime(11, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);
    Appointment::query()->create([
        'customer_id' => $customerA->id,
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'date' => $now->copy()->subHour(),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $service = app(OperationalStatsReadModelService::class);

    expect($service->summary($manager))->toMatchArray([
        'new_customers_today' => 1,
        'appointments_today' => 2,
        'pending_confirmations' => 2,
    ]);

    expect($service->summary($admin))->toMatchArray([
        'new_customers_today' => 2,
        'appointments_today' => 3,
        'pending_confirmations' => 3,
    ]);

    Carbon::setTestNow();
});

it('renders operational widget stats from the shared read model', function (): void {
    $now = Carbon::parse('2026-03-29 10:30:00');
    Carbon::setTestNow($now);

    $branch = Branch::factory()->create();

    $manager = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $manager->assignRole('Manager');

    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'created_at' => $now->copy()->subMinutes(15),
        'updated_at' => $now->copy()->subMinutes(15),
    ]);

    $patient = Patient::query()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    Appointment::query()->create([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => $now->copy()->setTime(15, 0),
        'status' => Appointment::STATUS_SCHEDULED,
    ]);

    $this->actingAs($manager);

    $widget = new class extends OperationalStatsWidget
    {
        public function stats(): array
        {
            return $this->getStats();
        }
    };

    $stats = $widget->stats();

    $statsByLabel = collect($stats)
        ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()])
        ->all();

    expect($stats)->toHaveCount(3)
        ->and($statsByLabel)->toMatchArray([
            'Khách hàng mới (Hôm nay)' => 1,
            'Lịch hẹn hôm nay' => 1,
            'Chờ xác nhận' => 1,
        ]);

    Carbon::setTestNow();
});
