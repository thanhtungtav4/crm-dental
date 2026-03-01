<?php

use Illuminate\Support\Facades\File;

it('configures global default notifications for filament actions', function (): void {
    $provider = File::get(app_path('Providers/AppServiceProvider.php'));

    expect($provider)
        ->toContain('configureFilamentActionNotifications')
        ->toContain('FilamentAction::configureUsing')
        ->toContain('failureNotificationTitle')
        ->toContain('unauthorizedNotificationTitle')
        ->toContain('rateLimitedNotificationTitle');
});

it('applies success notifications to high impact crm actions', function (): void {
    $appointmentsTable = File::get(app_path('Filament/Resources/Appointments/Tables/AppointmentsTable.php'));
    $customersTable = File::get(app_path('Filament/Resources/Customers/Tables/CustomersTable.php'));
    $planItemsManager = File::get(app_path('Filament/Resources/TreatmentPlans/RelationManagers/PlanItemsRelationManager.php'));
    $paymentsTable = File::get(app_path('Filament/Resources/Payments/Tables/PaymentsTable.php'));
    $invoicePaymentsManager = File::get(app_path('Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php'));
    $patientPaymentsManager = File::get(app_path('Filament/Resources/Patients/RelationManagers/PatientPaymentsRelationManager.php'));

    expect($appointmentsTable)
        ->toContain("Action::make('mark_late_arrival')")
        ->toContain("Action::make('mark_emergency')")
        ->toContain("Action::make('mark_walk_in')")
        ->toContain("->successNotificationTitle('Đã ghi nhận trễ giờ')")
        ->toContain("->successNotificationTitle('Đã ghi nhận ca khẩn cấp')")
        ->toContain("->successNotificationTitle('Đã ghi nhận khách walk-in')");

    expect($customersTable)
        ->toContain("Action::make('createAppointment')")
        ->toContain("->successNotificationTitle('Đã tạo lịch hẹn')");

    expect($planItemsManager)
        ->toContain('CreateAction::make()')
        ->toContain("Action::make('propose_for_patient')")
        ->toContain("Action::make('approve_by_patient')")
        ->toContain("Action::make('decline_by_patient')")
        ->toContain("Action::make('start_treatment')")
        ->toContain("Action::make('complete_treatment')")
        ->toContain("->successNotificationTitle('Đã thêm hạng mục điều trị')")
        ->toContain("->successNotificationTitle('Đã gửi đề xuất cho bệnh nhân')")
        ->toContain("->successNotificationTitle('Đã xác nhận bệnh nhân đồng ý')")
        ->toContain("->successNotificationTitle('Đã ghi nhận bệnh nhân từ chối')")
        ->toContain("->successNotificationTitle('Đã bắt đầu hạng mục điều trị')")
        ->toContain("->successNotificationTitle('Đã hoàn thành hạng mục điều trị')");

    expect($paymentsTable)
        ->toContain("Action::make('refund')")
        ->toContain("->successNotificationTitle('Đã tạo phiếu hoàn tiền')");

    expect($invoicePaymentsManager)
        ->toContain("Action::make('refund')")
        ->toContain("->successNotificationTitle('Đã tạo phiếu hoàn tiền')");

    expect($patientPaymentsManager)
        ->toContain("Action::make('refund')")
        ->toContain("->successNotificationTitle('Đã tạo phiếu hoàn tiền')");
});
