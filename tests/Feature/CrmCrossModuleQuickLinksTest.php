<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\File;

it('wires customer and appointment lists to related profile pages', function (): void {
    $customersTable = File::get(app_path('Filament/Resources/Customers/Tables/CustomersTable.php'));
    $appointmentsTable = File::get(app_path('Filament/Resources/Appointments/Tables/AppointmentsTable.php'));

    expect($customersTable)
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("CustomerResource::getUrl('edit'")
        ->toContain("'tab' => 'basic-info'");

    expect($appointmentsTable)
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("CustomerResource::getUrl('edit'")
        ->toContain("'tab' => 'appointments'")
        ->toContain('->openUrlInNewTab()');
});

it('shows linked visual counters on patient list for core crm flow', function (): void {
    $patientsTable = File::get(app_path('Filament/Resources/Patients/Tables/PatientsTable.php'));

    expect($patientsTable)
        ->toContain("TextColumn::make('appointments_count')")
        ->toContain("TextColumn::make('treatment_plans_count')")
        ->toContain("TextColumn::make('invoices_count')")
        ->toContain("'tab' => 'appointments'")
        ->toContain("'tab' => 'exam-treatment'")
        ->toContain("'tab' => 'payments'");
});

it('links invoice, payment, and treatment plan screens back to patient context', function (): void {
    $invoicesTable = File::get(app_path('Filament/Resources/Invoices/Tables/InvoicesTable.php'));
    $paymentsTable = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));
    $paymentViewPage = File::get(app_path('Filament/Resources/Payments/Pages/ViewPayment.php'));
    $paymentInfolist = File::get(app_path('Filament/Resources/Payments/Schemas/PaymentInfolist.php'));
    $receiptsExpenseTable = File::get(app_path('Filament/Resources/ReceiptsExpense/Tables/ReceiptsExpenseTable.php'));
    $treatmentPlansTable = File::get(app_path('Filament/Resources/TreatmentPlans/Tables/TreatmentPlansTable.php'));
    $treatmentSessionsTable = File::get(app_path('Filament/Resources/TreatmentSessions/Tables/TreatmentSessionsTable.php'));

    expect($invoicesTable)
        ->toContain("InvoiceResource::getUrl('edit'")
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("Action::make('open_patient_profile')");

    expect($paymentsTable)
        ->toContain("InvoiceResource::getUrl('edit'")
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("Action::make('view_patient_profile')")
        ->toContain("Action::make('refund')")
        ->toContain("->successNotificationTitle('Đã tạo phiếu hoàn tiền')");

    expect($paymentViewPage)
        ->toContain("Action::make('open_patient_profile')")
        ->toContain("Action::make('open_invoice')")
        ->toContain("Action::make('create_receipt_expense_voucher')")
        ->toContain("Action::make('print')")
        ->toContain("'tab' => 'payments'");

    expect($paymentInfolist)
        ->toContain('Section::make(\'Liên kết hồ sơ\')')
        ->toContain('Section::make(\'Chi tiết thanh toán\')')
        ->toContain('Section::make(\'Nội dung & đối soát\')')
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("InvoiceResource::getUrl('edit'");

    expect($treatmentPlansTable)
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("Action::make('open_patient_profile')")
        ->toContain("'return_url' => request()->fullUrl()")
        ->toContain("->successNotificationTitle('Đã duyệt kế hoạch điều trị')")
        ->toContain("->successNotificationTitle('Đã chuyển kế hoạch sang đang thực hiện')")
        ->toContain("->successNotificationTitle('Đã hoàn thành kế hoạch điều trị')")
        ->toContain("'tab' => 'exam-treatment'");

    expect($receiptsExpenseTable)
        ->toContain("Action::make('approve')")
        ->toContain("Action::make('post')")
        ->toContain("->successNotificationTitle('Đã duyệt phiếu thu/chi')")
        ->toContain("->successNotificationTitle('Đã hạch toán phiếu thu/chi')");

    expect($treatmentSessionsTable)
        ->toContain("PatientResource::getUrl('view'")
        ->toContain("TreatmentPlanResource::getUrl('edit'")
        ->toContain("Action::make('view_patient_profile')")
        ->toContain("Action::make('view_treatment_plan')")
        ->toContain("'return_url' => request()->fullUrl()");
});

it('renders major crm list screens after cross-link updates', function (string $routeName): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertSuccessful();
})->with([
    'customers' => 'filament.admin.resources.customers.index',
    'patients' => 'filament.admin.resources.patients.index',
    'appointments' => 'filament.admin.resources.appointments.index',
    'invoices' => 'filament.admin.resources.invoices.index',
    'payments' => 'filament.admin.resources.payments.index',
    'treatment plans' => 'filament.admin.resources.treatment-plans.index',
    'treatment sessions' => 'filament.admin.resources.treatment-sessions.index',
]);
