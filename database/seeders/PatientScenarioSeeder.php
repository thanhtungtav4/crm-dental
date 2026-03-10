<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MasterPatientDuplicate;
use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class PatientScenarioSeeder extends Seeder
{
    public const CANONICAL_PATIENT_CODE = 'PAT-QA-MPI-001';

    public const MERGED_PATIENT_CODE = 'PAT-QA-MPI-002';

    public const DUPLICATE_PHONE = '0909000999';

    public const MERGED_APPOINTMENT_NOTE = 'seed:patient-scenario:merge-appointment';

    public const MERGED_NOTE_TICKET_KEY = 'seed:patient-scenario:merge-note';

    public function run(): void
    {
        $branchIdsByCode = Branch::query()
            ->whereIn('code', ['HCM-Q1', 'HN-CG'])
            ->pluck('id', 'code');
        $userIdsByEmail = User::query()
            ->whereIn('email', [
                'admin@demo.ident.test',
                'doctor.cg@demo.ident.test',
                'cskh.q1@demo.ident.test',
                'cskh.cg@demo.ident.test',
            ])
            ->pluck('id', 'email');

        $canonicalBranchId = $branchIdsByCode->get('HCM-Q1');
        $mergedBranchId = $branchIdsByCode->get('HN-CG');
        $adminId = $userIdsByEmail->get('admin@demo.ident.test');

        if (! is_numeric($canonicalBranchId) || ! is_numeric($mergedBranchId) || ! is_numeric($adminId)) {
            return;
        }

        $canonicalCustomer = $this->upsertCustomer(
            branchId: (int) $canonicalBranchId,
            fullName: 'QA MPI Canonical Patient',
            phone: self::DUPLICATE_PHONE,
            email: 'qa.mpi.canonical@demo.ident.test',
            assignedTo: is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
                ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
                : null,
            sourceDetail: 'seed:patient-scenario:canonical',
            actorId: (int) $adminId,
        );

        $mergedCustomer = $this->upsertCustomer(
            branchId: (int) $mergedBranchId,
            fullName: 'QA MPI Merged Patient',
            phone: self::DUPLICATE_PHONE,
            email: 'qa.mpi.merged@demo.ident.test',
            assignedTo: is_numeric($userIdsByEmail->get('cskh.cg@demo.ident.test'))
                ? (int) $userIdsByEmail->get('cskh.cg@demo.ident.test')
                : null,
            sourceDetail: 'seed:patient-scenario:merged',
            actorId: (int) $adminId,
        );

        $canonicalPatient = $this->upsertPatient(
            customer: $canonicalCustomer,
            patientCode: self::CANONICAL_PATIENT_CODE,
            branchId: (int) $canonicalBranchId,
            fullName: 'QA MPI Canonical Patient',
            phone: self::DUPLICATE_PHONE,
            email: 'qa.mpi.canonical@demo.ident.test',
            doctorId: is_numeric($userIdsByEmail->get('doctor.cg@demo.ident.test'))
                ? (int) $userIdsByEmail->get('doctor.cg@demo.ident.test')
                : null,
            ownerStaffId: is_numeric($userIdsByEmail->get('cskh.q1@demo.ident.test'))
                ? (int) $userIdsByEmail->get('cskh.q1@demo.ident.test')
                : null,
            actorId: (int) $adminId,
        );

        $mergedPatient = $this->upsertPatient(
            customer: $mergedCustomer,
            patientCode: self::MERGED_PATIENT_CODE,
            branchId: (int) $mergedBranchId,
            fullName: 'QA MPI Merged Patient',
            phone: self::DUPLICATE_PHONE,
            email: 'qa.mpi.merged@demo.ident.test',
            doctorId: is_numeric($userIdsByEmail->get('doctor.cg@demo.ident.test'))
                ? (int) $userIdsByEmail->get('doctor.cg@demo.ident.test')
                : null,
            ownerStaffId: is_numeric($userIdsByEmail->get('cskh.cg@demo.ident.test'))
                ? (int) $userIdsByEmail->get('cskh.cg@demo.ident.test')
                : null,
            actorId: (int) $adminId,
        );

        Appointment::query()->updateOrCreate(
            ['note' => self::MERGED_APPOINTMENT_NOTE],
            [
                'customer_id' => $mergedPatient->customer_id,
                'patient_id' => $mergedPatient->id,
                'doctor_id' => is_numeric($userIdsByEmail->get('doctor.cg@demo.ident.test'))
                    ? (int) $userIdsByEmail->get('doctor.cg@demo.ident.test')
                    : null,
                'assigned_to' => is_numeric($userIdsByEmail->get('cskh.cg@demo.ident.test'))
                    ? (int) $userIdsByEmail->get('cskh.cg@demo.ident.test')
                    : null,
                'branch_id' => $mergedPatient->first_branch_id,
                'date' => self::mergeScenarioAppointmentAt(),
                'appointment_type' => 'consultation',
                'appointment_kind' => 'booking',
                'duration_minutes' => 45,
                'status' => Appointment::STATUS_SCHEDULED,
                'chief_complaint' => 'MPI merge follow-up scenario',
                'confirmed_at' => null,
                'reminder_hours' => 24,
                'is_walk_in' => false,
                'is_emergency' => false,
                'is_overbooked' => false,
            ],
        );

        Note::query()->updateOrCreate(
            ['ticket_key' => self::MERGED_NOTE_TICKET_KEY],
            [
                'patient_id' => $mergedPatient->id,
                'branch_id' => $mergedPatient->first_branch_id,
                'customer_id' => $mergedPatient->customer_id,
                'user_id' => (int) $adminId,
                'type' => Note::TYPE_GENERAL,
                'care_type' => 'mpi_merge_review',
                'care_channel' => 'internal',
                'care_status' => Note::CARE_STATUS_DONE,
                'care_at' => now()->subDay(),
                'care_mode' => 'manual',
                'is_recurring' => false,
                'content' => 'QA scenario note for MPI merge rollback verification.',
                'source_type' => Patient::class,
                'source_id' => $mergedPatient->id,
            ],
        );

        MasterPatientDuplicate::query()->updateOrCreate(
            [
                'identity_type' => 'phone',
                'identity_hash' => self::duplicateIdentityHash(),
                'status' => MasterPatientDuplicate::STATUS_OPEN,
            ],
            [
                'patient_id' => $mergedPatient->id,
                'branch_id' => $mergedPatient->first_branch_id,
                'identity_value' => self::DUPLICATE_PHONE,
                'matched_patient_ids' => [$canonicalPatient->id, $mergedPatient->id],
                'matched_branch_ids' => [$canonicalPatient->first_branch_id, $mergedPatient->first_branch_id],
                'confidence_score' => 98,
                'review_note' => 'Seeded MPI merge-ready scenario for local QA.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'metadata' => [
                    'scenario' => 'patient-module-local-seed',
                ],
            ],
        );
    }

    public static function duplicateIdentityHash(): string
    {
        return hash('sha256', 'phone|'.self::DUPLICATE_PHONE);
    }

    protected static function mergeScenarioAppointmentAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays(2)->setTime(10, 0);
    }

    protected function upsertCustomer(
        int $branchId,
        string $fullName,
        string $phone,
        ?string $email,
        ?int $assignedTo,
        string $sourceDetail,
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
            'source' => 'other',
            'source_detail' => $sourceDetail,
            'status' => 'converted',
            'assigned_to' => $assignedTo,
            'notes' => 'Seeded patient module QA scenario.',
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
            'gender' => 'female',
            'address' => 'Seed QA patient scenario',
            'primary_doctor_id' => $doctorId,
            'owner_staff_id' => $ownerStaffId,
            'first_visit_reason' => 'MPI merge review',
            'status' => 'active',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $patient->save();

        return $patient->fresh();
    }
}
