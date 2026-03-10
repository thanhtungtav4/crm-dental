<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class CareScenarioSeeder extends Seeder
{
    public const NO_SHOW_PATIENT_CODE = 'PAT-QA-CARE-001';

    public const REACTIVATION_PATIENT_CODE = 'PAT-QA-CARE-002';

    public const BLOCKED_REACTIVATION_PATIENT_CODE = 'PAT-QA-CARE-003';

    public const NO_SHOW_APPOINTMENT_NOTE = 'seed:care-scenario:no-show';

    public const ELIGIBLE_REACTIVATION_APPOINTMENT_NOTE = 'seed:care-scenario:reactivation-history';

    public const BLOCKED_HISTORY_APPOINTMENT_NOTE = 'seed:care-scenario:blocked-history';

    public const BLOCKED_FUTURE_APPOINTMENT_NOTE = 'seed:care-scenario:blocked-future';

    public function run(): void
    {
        $branchId = Branch::query()->where('code', 'HCM-Q1')->value('id');
        $userIdsByEmail = User::query()
            ->whereIn('email', [
                'admin@demo.ident.test',
                'doctor.q1@demo.ident.test',
                'cskh.q1@demo.ident.test',
            ])
            ->pluck('id', 'email');

        if (! is_numeric($branchId) || ! is_numeric($userIdsByEmail->get('admin@demo.ident.test'))) {
            return;
        }

        $adminId = (int) $userIdsByEmail->get('admin@demo.ident.test');
        $doctorId = is_numeric($userIdsByEmail->get('doctor.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('doctor.q1@demo.ident.test')
            : null;
        $frontDeskId = is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
            ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
            : null;

        $noShowPatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Care No Show',
            phone: '0909002091',
            email: 'qa.care.noshow@demo.ident.test',
            customerSourceDetail: 'seed:care-scenario:no-show',
            patientCode: self::NO_SHOW_PATIENT_CODE,
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );
        $eligiblePatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Care Reactivation Eligible',
            phone: '0909002092',
            email: 'qa.care.reactivation@demo.ident.test',
            customerSourceDetail: 'seed:care-scenario:reactivation-eligible',
            patientCode: self::REACTIVATION_PATIENT_CODE,
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );
        $blockedPatient = $this->upsertScenarioPatient(
            branchId: (int) $branchId,
            fullName: 'QA Care Reactivation Blocked',
            phone: '0909002093',
            email: 'qa.care.blocked@demo.ident.test',
            customerSourceDetail: 'seed:care-scenario:reactivation-blocked',
            patientCode: self::BLOCKED_REACTIVATION_PATIENT_CODE,
            ownerStaffId: $frontDeskId,
            doctorId: $doctorId,
            actorId: $adminId,
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::NO_SHOW_APPOINTMENT_NOTE],
            [
                'customer_id' => $noShowPatient->customer_id,
                'patient_id' => $noShowPatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $noShowPatient->first_branch_id,
                'date' => CarbonImmutable::now()->subDays(3)->setTime(9, 30),
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_NO_SHOW,
                'chief_complaint' => 'No-show recovery seed',
                'reminder_hours' => 12,
                'confirmed_at' => CarbonImmutable::now()->subDays(4),
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::ELIGIBLE_REACTIVATION_APPOINTMENT_NOTE],
            [
                'customer_id' => $eligiblePatient->customer_id,
                'patient_id' => $eligiblePatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $eligiblePatient->first_branch_id,
                'date' => CarbonImmutable::now()->subDays(150)->setTime(14, 0),
                'appointment_type' => 'follow_up',
                'appointment_kind' => 're_exam',
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_COMPLETED,
                'chief_complaint' => 'Reactivation history seed',
                'reminder_hours' => 24,
                'confirmed_at' => CarbonImmutable::now()->subDays(151),
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::BLOCKED_HISTORY_APPOINTMENT_NOTE],
            [
                'customer_id' => $blockedPatient->customer_id,
                'patient_id' => $blockedPatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $blockedPatient->first_branch_id,
                'date' => CarbonImmutable::now()->subDays(150)->setTime(10, 30),
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'duration_minutes' => 30,
                'status' => Appointment::STATUS_COMPLETED,
                'chief_complaint' => 'Blocked reactivation history seed',
                'reminder_hours' => 24,
                'confirmed_at' => CarbonImmutable::now()->subDays(151),
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::BLOCKED_FUTURE_APPOINTMENT_NOTE],
            [
                'customer_id' => $blockedPatient->customer_id,
                'patient_id' => $blockedPatient->id,
                'doctor_id' => $doctorId,
                'assigned_to' => $frontDeskId,
                'branch_id' => $blockedPatient->first_branch_id,
                'date' => CarbonImmutable::now()->addDays(5)->setTime(16, 0),
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'duration_minutes' => 45,
                'status' => Appointment::STATUS_SCHEDULED,
                'chief_complaint' => 'Future booking blocks reactivation',
                'reminder_hours' => 24,
                'confirmed_at' => null,
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );
    }

    protected function upsertScenarioPatient(
        int $branchId,
        string $fullName,
        string $phone,
        ?string $email,
        string $customerSourceDetail,
        string $patientCode,
        ?int $ownerStaffId,
        ?int $doctorId,
        int $actorId,
    ): Patient {
        $customer = Customer::query()
            ->where('branch_id', $branchId)
            ->where('source_detail', $customerSourceDetail)
            ->first() ?? new Customer;

        $customer->fill([
            'branch_id' => $branchId,
            'full_name' => $fullName,
            'phone' => $phone,
            'phone_search_hash' => Customer::phoneSearchHash($phone),
            'email' => $email,
            'email_search_hash' => Customer::emailSearchHash($email),
            'source' => 'other',
            'source_detail' => $customerSourceDetail,
            'status' => 'converted',
            'assigned_to' => $ownerStaffId,
            'notes' => 'Seeded care module QA scenario.',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $customer->save();

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
            'gender' => 'female',
            'address' => 'Seed care scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $ownerStaffId,
            'first_visit_reason' => 'Care automation validation',
            'status' => 'active',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $patient->save();

        return $patient->fresh();
    }
}
