<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DoctorBranchAssignment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentSchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeApptFixture(array $overrides = []): Appointment
{
    $branch = Branch::factory()->create();

    $doctor = User::factory()->create(['branch_id' => $branch->id]);
    $doctor->assignRole('Doctor');

    DoctorBranchAssignment::query()->updateOrCreate(
        ['user_id' => $doctor->id, 'branch_id' => $branch->id],
        ['is_active' => true, 'is_primary' => true, 'assigned_from' => null, 'assigned_until' => null],
    );

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    return Appointment::create(array_merge([
        'customer_id' => $customer->id,
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->subHour(), // past so outcome statuses are allowed
        'status' => Appointment::STATUS_SCHEDULED,
    ], $overrides));
}

function apptService(): AppointmentSchedulingService
{
    return app(AppointmentSchedulingService::class);
}

// ---------------------------------------------------------------------------
// Raw status guard
// ---------------------------------------------------------------------------

describe('Appointment — raw status guard', function (): void {
    it('blocks direct status write outside managed workflow', function (): void {
        $appt = makeApptFixture();

        expect(fn () => $appt->update(['status' => Appointment::STATUS_COMPLETED]))
            ->toThrow(ValidationException::class, 'AppointmentSchedulingService');
    });

    it('allows status write inside runWithinManagedWorkflow', function (): void {
        $appt = makeApptFixture();

        Appointment::runWithinManagedWorkflow(function () use ($appt): void {
            $appt->update(['status' => Appointment::STATUS_CONFIRMED]);
        });

        expect($appt->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
    });

    it('blocks invalid transition even inside managed workflow', function (): void {
        $appt = makeApptFixture(['status' => Appointment::STATUS_COMPLETED]);

        expect(fn () => Appointment::runWithinManagedWorkflow(function () use ($appt): void {
            $appt->update(['status' => Appointment::STATUS_NO_SHOW]);
        }))->toThrow(ValidationException::class, 'APPOINTMENT_STATE_INVALID');
    });
});

// ---------------------------------------------------------------------------
// transitionStatus — happy paths + audit
// ---------------------------------------------------------------------------

describe('Appointment — transitionStatus happy paths', function (): void {
    it('transitions scheduled → confirmed and writes audit with status_from/status_to', function (): void {
        $appt = makeApptFixture(['date' => now()->addDay()]);
        $actor = User::factory()->create();

        $result = apptService()->transitionStatus(
            $appt,
            Appointment::STATUS_CONFIRMED,
            ['actor_id' => $actor->id],
        );

        expect($result->status)->toBe(Appointment::STATUS_CONFIRMED);

        $log = AuditLog::query()
            ->where('entity_type', Appointment::class)
            ->where('entity_id', $appt->id)
            ->where('action', AuditLog::ACTION_UPDATE)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(Appointment::STATUS_CONFIRMED)
            ->and($log->metadata['trigger'])->toBe('manual_confirm');
    });

    it('transitions scheduled → cancelled with reason and writes cancel audit', function (): void {
        $appt = makeApptFixture();
        $actor = User::factory()->create();

        $result = apptService()->transitionStatus(
            $appt,
            Appointment::STATUS_CANCELLED,
            ['reason' => 'Bệnh nhân bận', 'actor_id' => $actor->id],
        );

        expect($result->status)->toBe(Appointment::STATUS_CANCELLED);

        $log = AuditLog::query()
            ->where('entity_type', Appointment::class)
            ->where('entity_id', $appt->id)
            ->where('action', AuditLog::ACTION_CANCEL)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(Appointment::STATUS_CANCELLED)
            ->and($log->metadata['reason'])->toBe('Bệnh nhân bận')
            ->and($log->metadata['trigger'])->toBe('manual_cancel');
    });

    it('transitions scheduled → no_show and writes no_show audit', function (): void {
        $appt = makeApptFixture(['date' => now()->subHour()]);
        $actor = User::factory()->create();

        $result = apptService()->transitionStatus(
            $appt,
            Appointment::STATUS_NO_SHOW,
            ['actor_id' => $actor->id],
        );

        expect($result->status)->toBe(Appointment::STATUS_NO_SHOW);

        $log = AuditLog::query()
            ->where('entity_type', Appointment::class)
            ->where('entity_id', $appt->id)
            ->where('action', AuditLog::ACTION_NO_SHOW)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(Appointment::STATUS_NO_SHOW)
            ->and($log->metadata['trigger'])->toBe('manual_no_show');
    });

    it('transitions in_progress → completed and writes complete audit', function (): void {
        $appt = makeApptFixture([
            'date' => now()->subHour(),
            'status' => Appointment::STATUS_IN_PROGRESS,
        ]);
        $actor = User::factory()->create();

        $result = apptService()->transitionStatus(
            $appt,
            Appointment::STATUS_COMPLETED,
            ['actor_id' => $actor->id],
        );

        expect($result->status)->toBe(Appointment::STATUS_COMPLETED);

        $log = AuditLog::query()
            ->where('entity_type', Appointment::class)
            ->where('entity_id', $appt->id)
            ->where('action', AuditLog::ACTION_COMPLETE)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(Appointment::STATUS_COMPLETED)
            ->and($log->metadata['trigger'])->toBe('manual_complete');
    });
});

// ---------------------------------------------------------------------------
// transitionStatus — failure paths
// ---------------------------------------------------------------------------

describe('Appointment — transitionStatus failure paths', function (): void {
    it('throws when transitioning to an unrecognised status string', function (): void {
        $appt = makeApptFixture();

        // normalizeStatus passes through unknown strings unchanged, so the state machine
        // then rejects the transition from scheduled → unknown with APPOINTMENT_STATE_INVALID.
        expect(fn () => apptService()->transitionStatus($appt, 'not_a_status'))
            ->toThrow(ValidationException::class, 'APPOINTMENT_STATE_INVALID');
    });

    it('throws when cancelling without a reason', function (): void {
        $appt = makeApptFixture();

        expect(fn () => apptService()->transitionStatus($appt, Appointment::STATUS_CANCELLED))
            ->toThrow(ValidationException::class, 'lý do hủy');
    });

    it('throws when transitioning from completed (terminal state)', function (): void {
        $appt = makeApptFixture([
            'date' => now()->subHour(),
            'status' => Appointment::STATUS_COMPLETED,
        ]);

        expect(fn () => apptService()->transitionStatus($appt, Appointment::STATUS_NO_SHOW))
            ->toThrow(ValidationException::class, 'APPOINTMENT_STATE_INVALID');
    });

    it('throws when marking outcome status on a future appointment', function (): void {
        $appt = makeApptFixture(['date' => now()->addDay()]);

        expect(fn () => apptService()->transitionStatus($appt, Appointment::STATUS_COMPLETED))
            ->toThrow(ValidationException::class, 'chưa diễn ra');
    });
});

// ---------------------------------------------------------------------------
// reschedule — happy path + audit
// ---------------------------------------------------------------------------

describe('Appointment — reschedule happy path', function (): void {
    it('reschedules and writes reschedule audit with from_at / to_at', function (): void {
        $appt = makeApptFixture(['date' => now()->addDay()]);
        $actor = User::factory()->create();

        $newDate = now()->addDays(3);

        $result = apptService()->reschedule(
            appointment: $appt,
            startAt: $newDate,
            reason: 'Bệnh nhân xin dời lịch',
        );

        expect($result->status)->toBe(Appointment::STATUS_RESCHEDULED)
            ->and($result->reschedule_reason)->toBe('Bệnh nhân xin dời lịch');

        $log = AuditLog::query()
            ->where('entity_type', Appointment::class)
            ->where('entity_id', $appt->id)
            ->where('action', AuditLog::ACTION_RESCHEDULE)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['status_to'])->toBe(Appointment::STATUS_RESCHEDULED)
            ->and($log->metadata['trigger'])->toBe('manual_reschedule')
            ->and($log->metadata['reason'])->toBe('Bệnh nhân xin dời lịch');
    });

    it('throws when rescheduling without a reason', function (): void {
        $appt = makeApptFixture(['date' => now()->addDay()]);

        expect(fn () => apptService()->reschedule($appt, now()->addDays(2), reason: ''))
            ->toThrow(ValidationException::class, 'lý do đổi lịch');
    });
});
