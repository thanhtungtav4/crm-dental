<?php

use App\Filament\Resources\MaterialIssueNotes\MaterialIssueNoteResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('hides finance tabs and actions from the doctor patient workspace', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('patient_code', 'PAT-QA-FIN-001')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'payments']))
        ->assertOk()
        ->assertSee('QA Finance Overdue')
        ->assertDontSee("setActiveTab('payments')", escape: false)
        ->assertDontSee('Thông tin thanh toán')
        ->assertDontSee('Phiếu thu');

    $this->actingAs($doctor)
        ->get(MaterialIssueNoteResource::getUrl('index'))
        ->assertForbidden();
});

it('hides finance tabs and blocks inventory for cskh personas', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('patient_code', 'PAT-QA-FIN-001')
        ->firstOrFail();

    $this->actingAs($cskh)
        ->get(PatientResource::getUrl('view', ['record' => $patient, 'tab' => 'payments']))
        ->assertOk()
        ->assertSee('QA Finance Overdue')
        ->assertDontSee("setActiveTab('payments')", escape: false)
        ->assertDontSee('Thông tin thanh toán')
        ->assertDontSee('Phiếu thu');

    $this->actingAs($cskh)
        ->get(MaterialIssueNoteResource::getUrl('index'))
        ->assertForbidden();
});
