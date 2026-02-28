<?php

use App\Models\Branch;
use App\Models\ClinicalNote;
use App\Models\ClinicalOrder;
use App\Models\ClinicalResult;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitEpisode;
use App\Services\EmrPatientPayloadBuilder;
use Illuminate\Support\Carbon;

it('runs order to result lifecycle and infers encounter linkage', function () {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalOrderDoctorForBranch($branch);
    $patient = makeClinicalOrderPatientForBranch($branch);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_SCHEDULED,
        'scheduled_at' => Carbon::parse('2026-03-08 09:00:00'),
        'planned_duration_minutes' => 30,
    ]);

    $note = ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'visit_episode_id' => $encounter->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => '2026-03-08',
        'examination_note' => 'Đau răng hàm',
    ]);

    $order = ClinicalOrder::query()->create([
        'clinical_note_id' => $note->id,
        'order_type' => 'xray',
        'status' => ClinicalOrder::STATUS_PENDING,
        'payload' => ['modality' => 'panorama'],
    ]);

    expect($order->order_code)->not->toBeEmpty()
        ->and((int) $order->patient_id)->toBe((int) $patient->id)
        ->and((int) $order->visit_episode_id)->toBe((int) $encounter->id)
        ->and((int) $order->branch_id)->toBe((int) $branch->id)
        ->and($order->status)->toBe(ClinicalOrder::STATUS_PENDING);

    $order->markInProgress();

    $result = ClinicalResult::query()->create([
        'clinical_order_id' => $order->id,
        'status' => ClinicalResult::STATUS_DRAFT,
        'payload' => ['attachment' => 'xray/panorama-20260308.jpg'],
    ]);

    $result->finalize(
        verifiedBy: $doctor->id,
        interpretation: 'Tổn thương quanh chóp răng 26.',
        notes: 'Đề nghị điều trị nội nha và tái khám sau 7 ngày.',
    );

    $order->refresh();
    $result->refresh();

    expect($result->result_code)->not->toBeEmpty()
        ->and((int) $result->patient_id)->toBe((int) $patient->id)
        ->and((int) $result->visit_episode_id)->toBe((int) $encounter->id)
        ->and((int) $result->branch_id)->toBe((int) $branch->id)
        ->and($result->status)->toBe(ClinicalResult::STATUS_FINAL)
        ->and($result->resulted_at)->not->toBeNull()
        ->and($result->verified_at)->not->toBeNull()
        ->and((int) $result->verified_by)->toBe((int) $doctor->id)
        ->and($order->status)->toBe(ClinicalOrder::STATUS_COMPLETED)
        ->and($order->completed_at)->not->toBeNull();
});

it('includes order and result aggregates in emr payload', function () {
    $branch = Branch::factory()->create();
    $doctor = makeClinicalOrderDoctorForBranch($branch);
    $patient = makeClinicalOrderPatientForBranch($branch);

    $encounter = VisitEpisode::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => VisitEpisode::STATUS_IN_PROGRESS,
        'scheduled_at' => Carbon::parse('2026-03-09 10:00:00'),
        'in_chair_at' => Carbon::parse('2026-03-09 10:15:00'),
        'planned_duration_minutes' => 45,
    ]);

    $order = ClinicalOrder::factory()
        ->forEncounter($encounter)
        ->create([
            'order_type' => 'lab',
            'status' => ClinicalOrder::STATUS_IN_PROGRESS,
            'ordered_by' => $doctor->id,
        ]);

    $result = ClinicalResult::factory()
        ->forOrder($order)
        ->create([
            'status' => ClinicalResult::STATUS_FINAL,
            'verified_by' => $doctor->id,
            'payload' => ['cbc' => 'normal'],
            'interpretation' => 'Kết quả trong giới hạn cho phép.',
        ]);

    $payload = app(EmrPatientPayloadBuilder::class)->build($patient->fresh());

    expect(data_get($payload, 'order.records'))->toBeArray()
        ->and(data_get($payload, 'result.records'))->toBeArray()
        ->and(data_get($payload, 'order.records.0.id'))->toBe((int) $order->id)
        ->and(data_get($payload, 'order.records.0.result_ids.0'))->toBe((int) $result->id)
        ->and(data_get($payload, 'result.records.0.id'))->toBe((int) $result->id)
        ->and(data_get($payload, 'result.records.0.clinical_order_id'))->toBe((int) $order->id);
});

function makeClinicalOrderDoctorForBranch(Branch $branch): User
{
    $doctor = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->updateOrCreate(
        [
            'user_id' => $doctor->id,
            'branch_id' => $branch->id,
        ],
        [
            'is_active' => true,
            'is_primary' => true,
        ],
    );

    return $doctor;
}

function makeClinicalOrderPatientForBranch(Branch $branch): Patient
{
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
    ]);

    return Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);
}
