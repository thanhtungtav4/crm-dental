<?php

use App\Filament\Pages\IntegrationSettings;
use App\Filament\Pages\OpsControlCenter;
use App\Filament\Pages\SystemSettings;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('keeps manager out of admin-only system pages', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(SystemSettings::getUrl())
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(IntegrationSettings::getUrl())
        ->assertForbidden();

    $this->actingAs($manager)
        ->get(OpsControlCenter::getUrl())
        ->assertForbidden();
});

it('still allows admin into admin-only system pages', function (): void {
    seed(LocalDemoDataSeeder::class);

    $admin = User::query()
        ->where('email', 'admin@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($admin)
        ->get(SystemSettings::getUrl())
        ->assertOk()
        ->assertSee('Cài đặt hệ thống');

    $this->actingAs($admin)
        ->get(IntegrationSettings::getUrl())
        ->assertOk()
        ->assertSee('Cài đặt tích hợp');

    $this->actingAs($admin)
        ->get(OpsControlCenter::getUrl())
        ->assertOk()
        ->assertSee('Trung tâm OPS');
});
