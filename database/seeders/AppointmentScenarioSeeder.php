<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOverbookingPolicy;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class AppointmentScenarioSeeder extends Seeder
{
    public const BASE_PATIENT_CODE = 'PAT-QA-APPT-001';

    public const OVERBOOK_PATIENT_CODE = 'PAT-QA-APPT-002';

    public const BASE_APPOINTMENT_NOTE = 'seed:appointment-scenario:base-slot';

    public const FUTURE_GUARD_APPOINTMENT_NOTE = 'seed:appointment-scenario:future-guard';

    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HCM-Q1')->value('id');
        $userIdsByEmail = User::query()
            ->whereIn('email', [
                'admin@demo.nhakhoaanphuc.test',
                'doctor.q1@demo.nhakhoaanphuc.test',
                'cskh.q1@demo.nhakhoaanphuc.test',
            ])
            ->pluck('id', 'email');

        if (! is_numeric($branchId) || ! is_numeric($userIdsByEmail->get('admin@demo.nhakhoaanphuc.test'))) {
            return;
        }

        $adminId = (int) $userIdsByEmail->get('admin@demo.nhakhoaanphuc.test');
        $doctorId = is_numeric($userIdsByEmail->get('doctor.q1@demo.nhakhoaanphuc.test'))
            ? (int) $userIdsByEmail->get('doctor.q1@demo.nhakhoaanphuc.test')
            : null;
        $frontDeskId = is_numeric($userIdsByEmail->get('cskh.q1@demo.nhakhoaanphuc.test'))
            ? (int) $userIdsByEmail->get('cskh.q1@demo.nhakhoaanphuc.test')
            : null;

        $baseCustomer = $this->upsertCustomer(
            branchId: (int) $branchId,
            fullName: 'QA Appointment Base',
            phone: '0909001091',
            email: 'qa.appointment.base@demo.nhakhoaanphuc.test',
            sourceDetail: 'seed:appointment-scenario:base',
            assignedTo: $frontDeskId,
            actorId: $adminId,
        );
        $overbookCustomer = $this->upsertCustomer(
            branchId: (int) $branchId,
            fullName: 'QA Appointment Overbook',
            phone: '0909001092',
            email: 'qa.appointment.overbook@demo.nhakhoaanphuc.test',
            sourceDetail: 'seed:appointment-scenario:overbook',
            assignedTo: $frontDeskId,
            actorId: $adminId,
        );

        $basePatient = $this->upsertPatient(
            customer: $baseCustomer,
            patientCode: self::BASE_PATIENT_CODE,
            branchId: (int) $branchId,
            fullName: 'QA Appointment Base',
            phone: '0909001091',
            email: 'qa.appointment.base@demo.nhakhoaanphuc.test',
            doctorId: $doctorId,
            ownerStaffId: $frontDeskId,
            actorId: $adminId,
        );
        $overbookPatient = $this->upsertPatient(
            customer: $overbookCustomer,
            patientCode: self::OVERBOOK_PATIENT_CODE,
            branchId: (int) $branchId,
            fullName: 'QA Appointment Overbook',
            phone: '0909001092',
            email: 'qa.appointment.overbook@demo.nhakhoaanphuc.test',
            doctorId: $doctorId,
            ownerStaffId: $frontDeskId,
            actorId: $adminId,
        );

        BranchOverbookingPolicy::query()->updateOrCreate(
            ['branch_id' => (int) $branchId],
            [
                'is_enabled' => true,
                'max_parallel_per_doctor' => 1,
                'require_override_reason' => true,
            ],
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::BASE_APPOINTMENT_NOTE],
            [
                'customer_id' => $basePatient->customer_id,
                'patient_id' => $basePatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $basePatient->first_branch_id,
                'date' => self::baseSlotAt(),
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_SCHEDULED,
                'chief_complaint' => 'Base slot for overbooking smoke',
                'reminder_hours' => 24,
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::FUTURE_GUARD_APPOINTMENT_NOTE],
            [
                'customer_id' => $overbookPatient->customer_id,
                'patient_id' => $overbookPatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $overbookPatient->first_branch_id,
                'date' => self::futureGuardAt(),
                'appointment_type' => 'follow_up',
                'appointment_kind' => 'booking',
                'duration_minutes' => 45,
                'status' => Appointment::STATUS_SCHEDULED,
                'chief_complaint' => 'Future temporal guard smoke',
                'reminder_hours' => 24,
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );
    }

    protected static function baseSlotAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(2)->setTime(15, 0);
    }

    protected static function futureGuardAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(3)->setTime(11, 0);
    }

    protected function upsertCustomer(
        int $branchId,
        string $fullName,
        string $phone,
        ?string $email,
        string $sourceDetail,
        ?int $assignedTo,
        int $actorId,
    ): Customer {
        $customer = Customer::query()
            ->where('branch_id', $branchId)
            ->where('source_detail', $sourceDetail)
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => $branchId,
            'full_name' => $fullName,
            'phone' => $phone,
            'phone_search_hash' => Customer::phoneSearchHash($phone),
            'email' => $email,
            'email_search_hash' => Customer::emailSearchHash($email),
            'source' => 'qa_seed',
            'source_detail' => $sourceDetail,
            'status' => 'converted',
            'assigned_to' => $assignedTo,
            'notes' => 'Seeded appointment module QA scenario.',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $customer->save();

        return $customer->fresh();
    }

    protected function upsertPatient(
        Customer $customer,
        string $patientCode,
        int $branchId,
        string $fullName,
        string $phone,
        ?string $email,
        ?int $doctorId,
        ?int $ownerStaffId,
        int $actorId,
    ): Patient {
        $patient = Patient::query()->firstOrNew([
            'patient_code' => $patientCode,
        ]);

        $patient->fill([
            'customer_id' => $customer->id,
            'patient_code' => $patientCode,
            'first_branch_id' => $branchId,
            'full_name' => $fullName,
            'phone' => $phone,
            'phone_search_hash' => Patient::phoneSearchHash($phone),
            'email' => $email,
            'email_search_hash' => Patient::emailSearchHash($email),
            'gender' => 'male',
            'address' => 'Seed appointment scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $ownerStaffId,
            'first_visit_reason' => 'Appointment workflow validation',
            'status' => 'active',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $patient->save();

        return $patient->fresh();
    }
}
