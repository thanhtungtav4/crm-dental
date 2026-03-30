<?php

use App\Models\ClinicSetting;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Services\PopupAnnouncementCenterReadModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the next pending delivery and formats its announcement payload', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $expiredAnnouncement = PopupAnnouncement::query()->create([
        'title' => 'Popup đã hết hạn',
        'message' => 'Không được hiện',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'require_ack' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subMinute(),
        'published_at' => now()->subDay(),
    ]);

    PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $expiredAnnouncement->id,
        'user_id' => $user->id,
        'status' => PopupAnnouncementDelivery::STATUS_PENDING,
        'delivered_at' => now()->subMinutes(5),
    ]);

    $announcement = PopupAnnouncement::query()->create([
        'code' => 'OPS-001',
        'title' => 'Popup đang chờ',
        'message' => 'Nội dung cần đọc',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'require_ack' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addHour(),
        'published_at' => now()->subMinute(),
    ]);

    $delivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'status' => PopupAnnouncementDelivery::STATUS_PENDING,
        'delivered_at' => now(),
    ]);

    $service = app(PopupAnnouncementCenterReadModelService::class);

    $pendingDelivery = $service->pendingDeliveryForUser($user->id);

    expect($pendingDelivery?->is($delivery))->toBeTrue()
        ->and($service->activeDeliveryForUser($user->id, $delivery->id)?->is($delivery))->toBeTrue()
        ->and($service->announcementPayload($pendingDelivery))->toMatchArray([
            'code' => 'OPS-001',
            'title' => 'Popup đang chờ',
            'message' => 'Nội dung cần đọc',
            'priority' => PopupAnnouncement::PRIORITY_INFO,
            'priority_label' => 'Thông tin',
            'require_ack' => true,
            'mode_label' => 'Cần xác nhận',
            'mode_classes' => 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200',
            'intro_text' => 'Cần xác nhận đã đọc trước khi tiếp tục thao tác.',
            'footer_text' => 'Popup sẽ chỉ biến mất sau khi bạn xác nhận đã đọc.',
            'can_dismiss' => false,
            'primary_action' => [
                'label' => 'Tôi đã đọc',
                'method' => 'acknowledge',
                'color' => 'primary',
                'target' => 'acknowledge',
            ],
            'close_action' => null,
            'dialog_id' => 'popup-announcement-'.$delivery->id,
            'title_id' => 'popup-announcement-'.$delivery->id.'-title',
            'meta_id' => 'popup-announcement-'.$delivery->id.'-meta',
            'body_id' => 'popup-announcement-'.$delivery->id.'-body',
            'meta_text' => 'Mã: OPS-001 · Bắt đầu: '.now()->subMinute()->format('d/m/Y H:i').' · Kết thúc: '.now()->addHour()->format('d/m/Y H:i'),
        ]);
});

it('formats dismissable popup presentation fields for the center view', function (): void {
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'code' => 'OPS-002',
        'title' => 'Popup đọc và đóng',
        'message' => 'Đọc xong có thể đóng',
        'priority' => PopupAnnouncement::PRIORITY_WARNING,
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
        'delivered_at' => now(),
    ]);

    $payload = app(PopupAnnouncementCenterReadModelService::class)->announcementPayload($delivery);

    expect($payload)->toMatchArray([
        'priority' => PopupAnnouncement::PRIORITY_WARNING,
        'priority_label' => 'Cảnh báo',
        'priority_classes' => 'border-amber-200 bg-amber-50 text-amber-700',
        'mode_label' => 'Đọc & đóng',
        'mode_classes' => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'intro_text' => 'Bạn có thể đóng popup khi đã nắm thông tin quan trọng này.',
        'footer_text' => 'Popup này chỉ hiển thị một lần để tránh gây nhiễu thao tác.',
        'can_dismiss' => true,
        'primary_action' => [
            'label' => 'Đóng thông báo',
            'method' => 'dismiss',
            'color' => 'gray',
            'target' => 'dismiss',
        ],
        'close_action' => [
            'label' => 'Đóng thông báo',
            'method' => 'dismiss',
            'target' => 'dismiss',
        ],
        'dialog_id' => 'popup-announcement-'.$delivery->id,
        'title_id' => 'popup-announcement-'.$delivery->id.'-title',
        'meta_id' => 'popup-announcement-'.$delivery->id.'-meta',
        'body_id' => 'popup-announcement-'.$delivery->id.'-body',
        'meta_text' => 'Mã: OPS-002 · Bắt đầu: '.now()->subMinute()->format('d/m/Y H:i'),
    ]);
});
