<?php

use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;

it('renders payment view page with grouped infolist sections and quick actions', function (): void {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $payment = Payment::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('filament.admin.resources.payments.view', ['record' => $payment]));

    $response->assertSuccessful()
        ->assertSee('Liên kết hồ sơ')
        ->assertSee('Chi tiết thanh toán')
        ->assertSeeText('Nội dung & đối soát')
        ->assertSee('Hồ sơ BN')
        ->assertSee('Mở hóa đơn')
        ->assertSee('Phiếu thu/chi');
});
