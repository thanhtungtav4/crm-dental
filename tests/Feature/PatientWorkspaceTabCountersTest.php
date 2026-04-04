<?php

use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchLog;
use App\Models\ClinicalNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Note;
use App\Models\Patient;
use App\Models\PatientPhoto;
use App\Models\Payment;
use App\Models\PlanItem;
use App\Models\Prescription;
use App\Models\TreatmentPlan;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Services\PatientOverviewReadModelService;

it('builds patient workspace tab counters through the shared read model', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

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
    ]);

    $patient = Patient::factory()->create([
        'customer_id' => $customer->id,
        'first_branch_id' => $branch->id,
        'full_name' => $customer->full_name,
        'phone' => $customer->phone,
        'email' => $customer->email,
    ]);

    $plan = TreatmentPlan::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => TreatmentPlan::STATUS_APPROVED,
    ]);

    $planItem = PlanItem::factory()->create([
        'treatment_plan_id' => $plan->id,
    ]);

    $session = TreatmentSession::factory()->create([
        'treatment_plan_id' => $plan->id,
        'plan_item_id' => $planItem->id,
        'doctor_id' => $doctor->id,
        'performed_at' => now(),
        'status' => 'scheduled',
    ]);

    $invoice = Invoice::factory()->create([
        'treatment_session_id' => $session->id,
        'treatment_plan_id' => $plan->id,
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'subtotal' => 900_000,
        'total_amount' => 900_000,
        'paid_amount' => 300_000,
        'status' => Invoice::STATUS_PARTIAL,
    ]);

    Payment::factory()->create([
        'invoice_id' => $invoice->id,
        'branch_id' => $branch->id,
        'received_by' => $manager->id,
        'direction' => 'receipt',
        'payment_source' => 'patient',
        'amount' => 300_000,
    ]);

    Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->addDay(),
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    Note::query()->create([
        'patient_id' => $patient->id,
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'user_id' => $manager->id,
        'type' => Note::TYPE_GENERAL,
        'care_type' => 'recall_recare',
        'care_status' => Note::CARE_STATUS_NOT_STARTED,
        'care_at' => now()->addDay(),
        'content' => 'Need recall follow-up',
    ]);

    ClinicalNote::query()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'date' => now()->toDateString(),
        'indications' => [],
        'indication_images' => [],
        'tooth_diagnosis_data' => [],
        'created_by' => $doctor->id,
        'updated_by' => $doctor->id,
    ]);

    PatientPhoto::query()->create([
        'patient_id' => $patient->id,
        'type' => PatientPhoto::TYPE_NORMAL,
        'date' => now()->toDateString(),
        'title' => 'Overview photo',
        'content' => ['https://example.test/photo.jpg'],
    ]);

    Prescription::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
    ]);

    BranchLog::factory()->create([
        'patient_id' => $patient->id,
        'from_branch_id' => $branch->id,
        'to_branch_id' => $branch->id,
        'moved_by' => $manager->id,
        'note' => 'Verified branch placement',
    ]);

    $page = new class extends ViewPatient
    {
        public function forceRecord(Patient $patient): void
        {
            $this->record = $patient;
        }
    };

    $page->forceRecord($patient->fresh());

    $service = app(PatientOverviewReadModelService::class);
    $counters = $service->tabCounters($patient->fresh());
    $serviceTabs = collect($service->workspaceTabs($patient->fresh(), $admin))->keyBy('id');
    $serviceRenderedTabs = collect($service->renderedWorkspaceTabs($serviceTabs->values()->all(), 'basic-info'))->keyBy('id');
    $workspaceViewState = $service->workspaceViewState($patient->fresh(), $admin, 'basic-info');
    $pageWorkspaceViewState = $page->workspaceViewState();
    $tabs = collect($pageWorkspaceViewState['tabs'])->keyBy('id');
    $renderedTabs = collect($pageWorkspaceViewState['rendered_tabs'])->keyBy('id');

    expect($counters)->toMatchArray([
        'treatment_plans' => 1,
        'invoices' => 1,
        'appointments' => 1,
        'notes' => 2,
        'clinical_notes' => 1,
        'exam_sessions' => 1,
        'photos' => 1,
        'prescriptions' => 1,
        'payments' => 1,
        'materials' => 0,
        'activity' => 7,
    ]);

    expect($tabs->keys()->all())->toBe($serviceTabs->keys()->all())
        ->and($tabs['exam-treatment']['count'])->toBe(2)
        ->and($tabs['photos']['count'])->toBe(1)
        ->and($tabs['appointments']['count'])->toBe(1)
        ->and($tabs['payments']['count'])->toBe(2)
        ->and($tabs['care']['count'])->toBe(2)
        ->and($tabs['activity-log']['count'])->toBe(7)
        ->and($renderedTabs['basic-info'])->toMatchArray([
            'button_id' => 'patient-workspace-tab-basic-info',
            'panel_id' => 'patient-workspace-panel-basic-info',
            'aria_selected' => 'true',
            'tabindex' => '0',
            'button_class' => 'crm-top-tab is-active',
        ])
        ->and($renderedTabs['payments'])->toMatchArray([
            'button_id' => 'patient-workspace-tab-payments',
            'panel_id' => 'patient-workspace-panel-payments',
            'aria_selected' => 'false',
            'tabindex' => '-1',
            'button_class' => 'crm-top-tab',
        ])
        ->and(collect($workspaceViewState['rendered_tabs'])->keyBy('id')->all())->toBe($serviceRenderedTabs->all())
        ->and($pageWorkspaceViewState['active_panel_id'])->toBe('patient-workspace-panel-basic-info')
        ->and($pageWorkspaceViewState['active_tab_button_id'])->toBe('patient-workspace-tab-basic-info')
        ->and($renderedTabs->all())->toBe($serviceRenderedTabs->all());
});

it('returns closed patient workspace capabilities when there is no authenticated actor', function (): void {
    $capabilities = app(PatientOverviewReadModelService::class)->workspaceCapabilities(null);

    expect($capabilities)->toBe([
        'prescriptions' => false,
        'appointments' => false,
        'payments' => false,
        'invoice_forms' => false,
        'forms' => false,
        'care' => false,
        'lab_materials' => false,
        'create_treatment_plan' => false,
        'create_invoice' => false,
        'create_appointment' => false,
        'create_payment' => false,
    ]);
});
