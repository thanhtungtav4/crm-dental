<?php

use App\Filament\Pages\Reports\OperationalKpiPack;
use App\Filament\Pages\ZaloZns;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('renders manager finance hot paths with seeded watchlist markers', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(InvoiceResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Hóa đơn')
        ->assertSee('INV-QA-FIN-001')
        ->assertSee('QA Finance Overdue');

    $this->actingAs($manager)
        ->get(PaymentResource::getUrl('index'))
        ->assertOk()
        ->assertSee('Thanh toán')
        ->assertSee('INV-QA-FIN-002')
        ->assertSee('QA Finance Reversal');
});

it('renders manager report and zns hot paths with seeded operational markers', function (): void {
    seed(LocalDemoDataSeeder::class);

    $manager = User::query()
        ->where('email', 'manager.q1@demo.nhakhoaanphuc.test')
        ->firstOrFail();

    $this->actingAs($manager)
        ->get(OperationalKpiPack::getUrl())
        ->assertOk()
        ->assertSee('KPI vận hành nha khoa')
        ->assertSee('Ngày snapshot')
        ->assertSee('Alert mở')
        ->assertSee('Chi nhánh');

    $this->actingAs($manager)
        ->get(ZaloZns::getUrl())
        ->assertOk()
        ->assertSee('Zalo ZNS')
        ->assertSee('Automation dead-letter')
        ->assertSee('Campaign đang chạy')
        ->assertSee('Nhắc lịch hẹn');
});
