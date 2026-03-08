<?php

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

use function Pest\Laravel\seed;

it('keeps finance and firewall closed for cskh while allowing lead conversion', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel('cskh.q1@demo.nhakhoaanphuc.test');

    assertForbiddenPath($page, '/admin/financial-dashboard');
    assertForbiddenPath($page, '/admin/receipts-expense');
    assertForbiddenPath($page, '/admin/firewall-ips');

    $page->navigate('/admin/customers')
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

it('allows doctor into patient workflows while finance and firewall stay forbidden', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $visiblePatient = Patient::query()
        ->where('patient_code', \Database\Seeders\AppointmentScenarioSeeder::BASE_PATIENT_CODE)
        ->where('first_branch_id', $doctor->branch_id)
        ->orderBy('id')
        ->firstOrFail();

    $page = loginToAdminPanel('doctor.q1@demo.nhakhoaanphuc.test');

    $page->navigate('/admin/patients')
        ->fill('.fi-ta-search-field input[type="search"]', (string) $visiblePatient->full_name)
        ->assertSee('Bệnh nhân')
        ->assertSee($visiblePatient->full_name);

    assertForbiddenPath($page, '/admin/financial-dashboard');
    assertForbiddenPath($page, '/admin/firewall-ips');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('lets manager pass mfa and see branch finance surfaces only', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel(
        'manager.q1@demo.nhakhoaanphuc.test',
        LocalDemoDataSeeder::demoMfaRecoveryCodesFor('manager.q1@demo.nhakhoaanphuc.test')[0] ?? null,
    );

    $page->navigate('/admin/financial-dashboard')
        ->assertSee('Dashboard Tài chính')
        ->navigate('/admin/receipts-expense')
        ->assertSee('Thu/chi')
        ->assertSee('PT-DEMO-Q1-001');

    assertForbiddenPath($page, '/admin/firewall-ips');

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('lets admin pass mfa and reach firewall management', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel(
        'admin@demo.nhakhoaanphuc.test',
        LocalDemoDataSeeder::demoMfaRecoveryCodesFor('admin@demo.nhakhoaanphuc.test')[0] ?? null,
    );

    $page->navigate('/admin/firewall-ips')
        ->assertSee('Tường Lửa IP')
        ->assertSee('Thêm IP của tôi')
        ->assertSee('Tạo mới')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('renders high-risk admin create forms that depend on schema utility injection', function (): void {
    seed(LocalDemoDataSeeder::class);

    $page = loginToAdminPanel(
        'admin@demo.nhakhoaanphuc.test',
        LocalDemoDataSeeder::demoMfaRecoveryCodesFor('admin@demo.nhakhoaanphuc.test')[0] ?? null,
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

    $page->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

function loginToAdminPanel(string $email, ?string $recoveryCode = null): PendingAwaitablePage|AwaitableWebpage
{
    $page = visit('/admin/login');

    $page->assertSee('Đăng nhập')
        ->fill('input[type="email"]', $email)
        ->fill('input[type="password"]', LocalDemoDataSeeder::DEFAULT_DEMO_PASSWORD)
        ->click('button[type="submit"]');

    if ($recoveryCode !== null) {
        $page->assertSee('Xác thực hai yếu tố')
            ->click('a[href="#"]')
            ->fill('input[placeholder="abcdef-98765"]', $recoveryCode)
            ->click('button[type="submit"]');
    }

    return $page->assertPathIs('/admin');
}

function assertForbiddenPath(PendingAwaitablePage|AwaitableWebpage $page, string $path): PendingAwaitablePage|AwaitableWebpage
{
    return $page->navigate($path)
        ->assertPathIs($path)
        ->assertSee('403');
}
