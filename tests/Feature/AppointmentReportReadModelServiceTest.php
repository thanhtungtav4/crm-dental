<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\AppointmentReportReadModelService;

it('summarizes appointment metrics within selected branches', function (): void {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $doctorA = User::factory()->create(['branch_id' => $branchA->id]);
    $doctorA->assignRole('Doctor');

    $doctorB = User::factory()->create(['branch_id' => $branchB->id]);
    $doctorB->assignRole('Doctor');

    $customerA = Customer::factory()->create(['branch_id' => $branchA->id]);
    $patientA = Patient::factory()->create([
        'customer_id' => $customerA->id,
        'first_branch_id' => $branchA->id,
    ]);

    $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
    $patientB = Patient::factory()->create([
        'customer_id' => $customerB->id,
        'first_branch_id' => $branchB->id,
    ]);

    $appointmentA = Appointment::factory()->create([
        'patient_id' => $patientA->id,
        'doctor_id' => $doctorA->id,
        'branch_id' => $branchA->id,
        'date' => now()->subDay()->setTime(9, 0),
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    $visitEpisodeA = $appointmentA->visitEpisode()->first();

    if (! $visitEpisodeA instanceof VisitEpisode) {
        $visitEpisodeA = VisitEpisode::query()->create([
            'appointment_id' => $appointmentA->id,
            'patient_id' => $patientA->id,
            'doctor_id' => $doctorA->id,
            'branch_id' => $branchA->id,
            'status' => VisitEpisode::STATUS_COMPLETED,
            'scheduled_at' => now(),
        ]);
    }

    $visitEpisodeA->forceFill([
        'waiting_minutes' => 15,
        'chair_minutes' => 45,
        'overrun_minutes' => 5,
    ])->save();

    $appointmentB = Appointment::factory()->create([
        'patient_id' => $patientB->id,
        'doctor_id' => $doctorB->id,
        'branch_id' => $branchB->id,
        'date' => now()->subDay()->setTime(10, 0),
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => 'Patient requested reschedule later',
    ]);

    $visitEpisodeB = $appointmentB->visitEpisode()->first();

    if (! $visitEpisodeB instanceof VisitEpisode) {
        $visitEpisodeB = VisitEpisode::query()->create([
            'appointment_id' => $appointmentB->id,
            'patient_id' => $patientB->id,
            'doctor_id' => $doctorB->id,
            'branch_id' => $branchB->id,
            'status' => VisitEpisode::STATUS_CANCELLED,
            'scheduled_at' => now(),
        ]);
    }

    $visitEpisodeB->forceFill([
        'waiting_minutes' => 120,
        'chair_minutes' => 10,
        'overrun_minutes' => 0,
    ])->save();

    $service = app(AppointmentReportReadModelService::class);
    $reportDate = now()->subDay()->toDateString();

    expect($service->appointmentSummary([$branchA->id], $reportDate, $reportDate))
        ->toBe([
            'total' => 1,
            'new' => 0,
            'cancelled' => 0,
            'completed' => 1,
            'avg_waiting' => 15.0,
            'avg_chair' => 45.0,
            'avg_overrun' => 5.0,
        ])
        ->and($service->appointmentSummaryStatsPayload([$branchA->id], $reportDate, $reportDate))
        ->toBe([
            ['label' => 'Tổng lịch hẹn', 'value' => '1'],
            ['label' => 'Lịch hẹn mới', 'value' => '0'],
            ['label' => 'Lịch hẹn bị hủy', 'value' => '0'],
            ['label' => 'Hoàn thành', 'value' => '1'],
            ['label' => 'Waiting TB (phút)', 'value' => '15.0'],
            ['label' => 'Chair TB (phút)', 'value' => '45.0'],
            ['label' => 'Overrun TB (phút)', 'value' => '5.0'],
        ]);

    expect($service->operationalStatusMetrics([$branchA->id], $reportDate, $reportDate))
        ->toBe([
            'total' => 1,
            'scheduled' => 0,
            'confirmed' => 0,
            'in_progress' => 0,
            'completed' => 1,
            'no_show' => 0,
        ]);
});

it('returns empty appointment readers for inaccessible branch selections', function (): void {
    $service = app(AppointmentReportReadModelService::class);

    expect($service->appointmentQuery([])->get())->toHaveCount(0)
        ->and($service->appointmentSummary([], now()->toDateString(), now()->toDateString()))->toBe([
            'total' => 0,
            'new' => 0,
            'cancelled' => 0,
            'completed' => 0,
            'avg_waiting' => 0.0,
            'avg_chair' => 0.0,
            'avg_overrun' => 0.0,
        ])
        ->and($service->appointmentSummaryStatsPayload([], now()->toDateString(), now()->toDateString()))->toBe([
            ['label' => 'Tổng lịch hẹn', 'value' => '0'],
            ['label' => 'Lịch hẹn mới', 'value' => '0'],
            ['label' => 'Lịch hẹn bị hủy', 'value' => '0'],
            ['label' => 'Hoàn thành', 'value' => '0'],
            ['label' => 'Waiting TB (phút)', 'value' => '0.0'],
            ['label' => 'Chair TB (phút)', 'value' => '0.0'],
            ['label' => 'Overrun TB (phút)', 'value' => '0.0'],
        ])
        ->and($service->operationalStatusMetrics([], now()->toDateString(), now()->toDateString()))->toBe([
            'total' => 0,
            'scheduled' => 0,
            'confirmed' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'no_show' => 0,
        ]);
});
