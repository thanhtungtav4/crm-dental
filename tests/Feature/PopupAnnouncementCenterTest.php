<?php

use App\Livewire\PopupAnnouncementCenter;
use App\Models\ClinicSetting;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use Livewire\Livewire;

it('shows pending popup once and allows dismiss when ack is not required', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('CSKH');
    $sender = User::factory()->create();
    $sender->assignRole('Manager');
    $this->actingAs($sender);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup không bắt buộc ack',
        'message' => 'Nội dung popup test dismiss',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'require_ack' => false,
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $delivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'status' => PopupAnnouncementDelivery::STATUS_PENDING,
        'delivered_at' => now()->subMinute(),
    ]);

    $this->actingAs($user);

    Livewire::test(PopupAnnouncementCenter::class)
        ->assertSet('activeDeliveryId', $delivery->id)
        ->assertSee('Popup không bắt buộc ack')
        ->call('dismiss')
        ->assertSet('activeDeliveryId', null);

    expect($delivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_DISMISSED)
        ->and($delivery->dismissed_at)->not->toBeNull();
});

it('requires explicit acknowledge when popup is marked as require_ack', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Doctor');
    $sender = User::factory()->create();
    $sender->assignRole('Manager');
    $this->actingAs($sender);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup bắt buộc xác nhận',
        'message' => 'Nội dung popup bắt buộc ack',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['Doctor'],
        'target_branch_ids' => [],
        'require_ack' => true,
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $delivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'status' => PopupAnnouncementDelivery::STATUS_PENDING,
        'delivered_at' => now()->subMinute(),
    ]);

    $this->actingAs($user);

    Livewire::test(PopupAnnouncementCenter::class)
        ->assertSet('activeDeliveryId', $delivery->id)
        ->assertSee('Tôi đã đọc')
        ->call('dismiss')
        ->assertSet('activeDeliveryId', $delivery->id)
        ->call('acknowledge')
        ->assertSet('activeDeliveryId', null);

    expect($delivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED)
        ->and($delivery->acknowledged_at)->not->toBeNull();
});
