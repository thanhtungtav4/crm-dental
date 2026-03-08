<?php

use App\Filament\Pages\DeliveryOpsCenter;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('allows admin manager and doctor personas to access the delivery ops center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Điều phối điều trị')
        ->assertSee('Lối tắt delivery');
})->with([
    'admin' => 'admin@demo.nhakhoaanphuc.test',
    'manager' => 'manager.q1@demo.nhakhoaanphuc.test',
    'doctor' => 'doctor.q1@demo.nhakhoaanphuc.test',
]);

it('blocks cskh persona from accessing the delivery ops center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($cskh)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertForbidden();
});

it('renders all q1 delivery scenarios for q1 manager persona', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Workflow điều trị')
        ->assertSee('Hồ sơ lâm sàng')
        ->assertSee('Cảnh báo kho')
        ->assertSee('Labo & gia công')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent')
        ->assertSee('QA Inventory Low Stock Composite')
        ->assertSee('FO-QA-SUP-001');
});

it('shows treatment and clinical delivery sections for q1 doctor without inventory and labo leakage', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertSee('Workflow điều trị')
        ->assertSee('Hồ sơ lâm sàng')
        ->assertSee('QA Treatment Workflow Plan')
        ->assertSee('QA Clinical Consent')
        ->assertDontSee('QA Inventory Low Stock Composite')
        ->assertDontSee('FO-QA-SUP-001');
});

it('keeps q1 delivery scenarios out of scope for a cau giay doctor', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.cg@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(DeliveryOpsCenter::getUrl())
        ->assertOk()
        ->assertDontSee('QA Treatment Workflow Plan')
        ->assertDontSee('QA Clinical Consent')
        ->assertDontSee('QA Inventory Low Stock Composite')
        ->assertDontSee('FO-QA-SUP-001');
});
