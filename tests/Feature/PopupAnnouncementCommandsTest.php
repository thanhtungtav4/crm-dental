<?php

use App\Models\ClinicSetting;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;

it('fails strict dispatch command when popup module is disabled', function (): void {
    $this->artisan('popups:dispatch-due', ['--strict' => true])
        ->expectsOutputToContain('Popup module đang tắt, strict mode trả về lỗi.')
        ->assertFailed();
});

it('dispatches due popup announcements via artisan command', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $recipient = User::factory()->create();
    $recipient->assignRole('CSKH');

    PopupAnnouncement::query()->create([
        'title' => 'Popup artisan dispatch',
        'message' => 'Nội dung popup command test',
        'status' => PopupAnnouncement::STATUS_SCHEDULED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'starts_at' => now()->subMinute(),
    ]);

    $this->artisan('popups:dispatch-due')
        ->expectsOutputToContain('deliveries_created=1')
        ->assertSuccessful();

    expect(PopupAnnouncementDelivery::query()->count())->toBe(1)
        ->and(PopupAnnouncementDelivery::query()->where('user_id', $recipient->id)->exists())->toBeTrue();
});

it('prunes old dismissed popup deliveries by retention days', function (): void {
    ClinicSetting::setValue('popup.retention_days', 30, [
        'group' => 'popup',
        'value_type' => 'integer',
    ]);

    $user = User::factory()->create();

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup prune test',
        'message' => 'Nội dung popup prune',
        'status' => PopupAnnouncement::STATUS_CANCELLED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
    ]);

    $delivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'status' => PopupAnnouncementDelivery::STATUS_DISMISSED,
        'dismissed_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $this->artisan('popups:prune')
        ->expectsOutputToContain('deliveries_deleted=1')
        ->assertSuccessful();

    expect(PopupAnnouncementDelivery::query()->whereKey($delivery->id)->exists())->toBeFalse();
});
