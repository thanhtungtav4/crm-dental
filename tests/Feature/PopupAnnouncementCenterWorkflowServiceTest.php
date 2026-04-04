<?php

use App\Models\ClinicSetting;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Services\PopupAnnouncementCenterWorkflowService;

it('returns the active popup state and marks the delivery as seen', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup chao buoi sang',
        'message' => 'Nội dung popup dang cho xem',
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

    $state = app(PopupAnnouncementCenterWorkflowService::class)->refreshForUser($user->id);

    expect($state)->not->toBeNull()
        ->and($state)->toMatchArray([
            'delivery_id' => $delivery->id,
        ])
        ->and(data_get($state, 'announcement.title'))->toBe('Popup chao buoi sang')
        ->and($delivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
        ->and($delivery->seen_at)->not->toBeNull();
});

it('returns null after acknowledging the active popup delivery', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Doctor');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup can xac nhan',
        'message' => 'Can bam da doc',
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

    app(PopupAnnouncementCenterWorkflowService::class)->refreshForUser($user->id);

    $nextState = app(PopupAnnouncementCenterWorkflowService::class)
        ->acknowledgeForUser($user->id, $delivery->id);

    expect($nextState)->toBeNull()
        ->and($delivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED)
        ->and($delivery->acknowledged_at)->not->toBeNull();
});

it('keeps the popup active when dismiss is attempted for required acknowledgement', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('Doctor');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup bat buoc ack',
        'message' => 'Khong duoc dong bang dismiss',
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

    app(PopupAnnouncementCenterWorkflowService::class)->refreshForUser($user->id);

    $nextState = app(PopupAnnouncementCenterWorkflowService::class)
        ->dismissForUser($user->id, $delivery->id);

    expect($nextState)->not->toBeNull()
        ->and($nextState)->toMatchArray([
            'delivery_id' => $delivery->id,
        ])
        ->and($delivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
        ->and($delivery->dismissed_at)->toBeNull();
});
