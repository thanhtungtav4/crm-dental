<?php

use App\Filament\Pages\DeliveryOpsCenter;
use App\Filament\Pages\IntegrationSettings;
use App\Filament\Pages\OpsControlCenter;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\PatientMedicalRecords\PatientMedicalRecordResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\AppointmentScenarioSeeder;
use Database\Seeders\GovernanceScenarioSeeder;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('renders admin hot paths with seeded governance and control-plane markers', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();
    $assignedDoctor = User::query()
        ->where('email', GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)
        ->firstOrFail();
    $hiddenUser = User::query()
        ->where('email', GovernanceScenarioSeeder::HIDDEN_USER_EMAIL)
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Trung tâm OPS')
        ->assertSee('Readiness signoff fixture')
        ->assertSee('Governance & audit scope');

    $this->actingAs($admin)
        ->get(IntegrationSettings::getUrl())
        ->assertOk()
        ->assertSee('Cài đặt tích hợp')
        ->assertSee('Web Lead API')
        ->assertSee('EMR');

    $this->actingAs($admin)
        ->get(UserResource::getUrl('edit', [
            'record' => $assignedDoctor,
        ]))
        ->assertOk()
        ->assertSee('Người dùng')
        ->assertSee(GovernanceScenarioSeeder::ASSIGNED_DOCTOR_EMAIL)
        ->assertSee('Vai trò');

    $this->actingAs($admin)
        ->get(UserResource::getUrl('edit', [
            'record' => $hiddenUser,
        ]))
        ->assertOk()
        ->assertSee(GovernanceScenarioSeeder::HIDDEN_USER_EMAIL);

    $this->actingAs($admin)
        ->get(AuditLogResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Audit logs')
        ->assertSee('Entity')
        ->assertSee('Hành động')
        ->assertSee('Người thực hiện');
});

it('renders doctor hot paths with seeded delivery, patient, appointment, and emr markers', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $clinicalPatient = Patient::query()
        ->where('patient_code', 'PAT-QA-CLIN-001')
        ->firstOrFail();

    $appointment = Appointment::query()
        ->where('note', AppointmentScenarioSeeder::BASE_APPOINTMENT_NOTE)
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Điều phối điều trị')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent');

    $this->actingAs($doctor)
        ->get(PatientResource::getUrl('view', [
            'record' => $clinicalPatient,
            'tab' => 'exam-treatment',
        ]))
        ->assertOk()
        ->assertSee('QA Clinical Consent')
        ->assertSee('Kế hoạch điều trị');

    $this->actingAs($doctor)
        ->get(PatientMedicalRecordResource::getUrl('create', [
            'patient_id' => $clinicalPatient->id,
        ]))
        ->assertOk()
        ->assertSee('Hồ sơ y tế')
        ->assertSee('QA Clinical Consent');

    $this->actingAs($doctor)
        ->get(AppointmentResource::getUrl('edit', [
            'record' => $appointment,
        ]))
        ->assertOk()
        ->assertSee('Lịch hẹn')
        ->assertSee('QA Appointment Base')
        ->assertSee('Base slot for overbooking smoke');
});
