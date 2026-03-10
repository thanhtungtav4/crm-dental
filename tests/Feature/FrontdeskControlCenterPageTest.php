<?php

use App\Filament\Pages\FrontdeskControlCenter;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('allows admin, manager, and cskh personas to access the frontdesk control center', function (string $email): void {
    seed(LocalDemoDataSeeder::class);

    $user = User::query()
        ->where('email', $email)
        ->firstOrFail();

    $this->actingAs($user)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Điều phối front-office')
        ->assertSee('Lead pipeline')
        ->assertSee('Lịch hẹn cần bàn giao')
        ->assertSee('Queue CSKH')
        ->assertSee('Khách hàng')
        ->assertSee('Queue ưu tiên');
})->with([
    'admin' => 'admin@demo.ident.test',
    'manager' => 'manager.q1@demo.ident.test',
    'cskh' => 'cskh.q1@demo.ident.test',
]);

it('blocks doctor persona from accessing the frontdesk control center', function (): void {
    seed(LocalDemoDataSeeder::class);

    $doctor = User::query()
        ->where('email', 'doctor.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($doctor)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertForbidden();
});

it('renders seeded lead, appointment, and care rows for the q1 frontdesk persona only within branch scope', function (): void {
    seed(LocalDemoDataSeeder::class);

    $cskh = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($cskh)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Pham Minh Chau')
        ->assertSee('QA Appointment Base')
        ->assertSee('Nguyen Thi Thu Trang')
        ->assertDontSee('Le Van Nam')
        ->assertDontSee('Vu Thi Hong Nhung');
});
