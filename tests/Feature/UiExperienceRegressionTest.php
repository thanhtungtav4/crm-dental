<?php

use App\Filament\Pages\CustomerCare;
use App\Filament\Pages\FrontdeskControlCenter;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('redirects guests from the root route to the admin login page', function (): void {
    $this->get('/')
        ->assertRedirect('/admin/login');
});

it('redirects authenticated staff from the root route to the admin dashboard', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get('/')
        ->assertRedirect('/admin');
});

it('renders the frontdesk subheading only once', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $response = $this->actingAs($staff)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk();

    expect(substr_count(
        $response->getContent(),
        'Nhìn nhanh lead đang mở, lịch hẹn sắp tới và queue CSKH để điều phối trong ngày mà không cần nhảy qua nhiều module.',
    ))->toBe(1);
});

it('renders accessible tab semantics on customer care and patient workspace pages', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('first_branch_id', $staff->branch_id)
        ->orderBy('id')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get(CustomerCare::getUrl())
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls=', false);

    $this->actingAs($staff)
        ->get("/admin/patients/{$patient->id}?tab=basic-info")
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls=', false);
});
