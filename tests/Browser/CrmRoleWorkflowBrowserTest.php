<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

use function Pest\Laravel\seed;

it('lets cskh work the frontdesk queue and convert a lead into a patient', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel('cskh.q1@demo.ident.test');

    $page->navigate('/admin/frontdesk-control-center')
        ->assertSee('Điều phối front-office')
        ->assertSee('Lead pipeline')
        ->assertSee('Pham Minh Chau')
        ->assertSee('QA Appointment Base')
        ->assertSee('Nguyen Thi Thu Trang')
        ->assertDontSee('Le Van Nam')
        ->navigate('/admin/customers')
        ->fill('.fi-ta-search-field input[type="search"]', 'Pham Minh Chau')
        ->assertSee('Pham Minh Chau')
        ->click('Xác nhận thành bệnh nhân')
        ->click('Xác nhận');

    $page->assertSee('Đã chuyển thành bệnh nhân')
        ->assertSee('Đã chuyển đổi')
        ->click('Pham Minh Chau')
        ->assertPathBeginsWith('/admin/patients/')
        ->assertSee('Đặt lịch hẹn')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('lets doctor work delivery and patient workflows from the seeded q1 branch', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $visiblePatient = Patient::query()
        ->where('patient_code', \Database\Seeders\AppointmentScenarioSeeder::BASE_PATIENT_CODE)
        ->where('first_branch_id', $doctor->branch_id)
        ->orderBy('id')
        ->firstOrFail();

    $page = loginToAdminPanel('doctor.q1@demo.ident.test');

    $page->navigate('/admin/delivery-ops-center')
        ->assertSee('Điều phối điều trị')
        ->assertSee('Workflow điều trị')
        ->assertSee('Hồ sơ lâm sàng')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent')
        ->assertDontSee('QA Inventory Low Stock Composite')
        ->assertDontSee('FO-QA-SUP-001')
        ->navigate('/admin/patients')
        ->fill('.fi-ta-search-field input[type="search"]', (string) $visiblePatient->full_name)
        ->assertSee('Bệnh nhân')
        ->assertSee($visiblePatient->full_name);

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('lets manager pass mfa and use branch-scoped finance and zns surfaces', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel(
        'manager.q1@demo.ident.test',
        LocalDemoDataSeeder::demoMfaRecoveryCodesFor('manager.q1@demo.ident.test')[0] ?? null,
    );

    $page->navigate('/admin/financial-dashboard')
        ->assertSee('Dashboard Tài chính')
        ->navigate('/admin/receipts-expense')
        ->assertSee('Thu/chi')
        ->assertSee('PT-DEMO-Q1-001')
        ->navigate('/admin/zalo-zns')
        ->assertSee('Zalo ZNS')
        ->assertSee('Automation dead-letter');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('shows finance and governance signals in the ops control center for admin', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel(
        'admin@demo.ident.test',
        LocalDemoDataSeeder::demoMfaRecoveryCodesFor('admin@demo.ident.test')[0] ?? null,
    );

    $pages = [
        '/admin/invoices/create' => 'Thông tin hóa đơn',
        '/admin/treatment-plans/create' => 'Thông tin chung',
        '/admin/receipts-expense/create' => 'Thông tin phiếu',
        '/admin/factory-orders/create' => 'Nhà cung cấp labo',
    ];

    foreach ($pages as $path => $expectedText) {
        $page->navigate($path)
            ->assertPathIs($path)
            ->assertSee($expectedText);
    }

    $page->navigate('/admin/ops-control-center')
        ->assertSee('Trung tâm OPS')
        ->assertSee('Finance & collections')
        ->assertSee('INV-QA-FIN-001')
        ->assertSee('QA-FIN-REV-RECEIPT')
        ->assertSee('Governance & audit scope')
        ->assertSee('qa.gov.assigned@demo.ident.test')
        ->assertSee('qa.gov.hidden@demo.ident.test')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

function loginToAdminPanel(string $email, ?string $recoveryCode = null): PendingAwaitablePage|AwaitableWebpage
{
    $page = visit('/admin/login');

    $page->fill('input[type="email"]', $email)
        ->fill('input[type="password"]', LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD)
        ->click('button[type="submit"]');

    if ($recoveryCode !== null) {
        $page->click('a[href="#"]')
            ->fill('input[placeholder="abcdef-98765"]', $recoveryCode)
            ->click('button[type="submit"]');
    }

    return $page->assertPathIs('/admin');
}
