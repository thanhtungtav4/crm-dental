<?php

use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Services\PopupAnnouncementDeliveryWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks popup deliveries as seen through the workflow service', function (): void {
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup workflow seen',
        'message' => 'Seen flow',
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
        'display_count' => 0,
    ]);

    $updatedDelivery = app(PopupAnnouncementDeliveryWorkflowService::class)->markSeen($delivery);

    expect($updatedDelivery->status)->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
        ->and($updatedDelivery->seen_at)->not->toBeNull()
        ->and((int) $updatedDelivery->display_count)->toBe(1)
        ->and($updatedDelivery->last_displayed_at)->not->toBeNull();
});

it('supports workflow delivery transitions through the model boundary', function (): void {
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup workflow boundary',
        'message' => 'Boundary flow',
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
        'display_count' => 0,
    ]);

    $seenDelivery = $delivery->markSeenViaWorkflow();

    expect($seenDelivery->status)->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
        ->and($seenDelivery->seen_at)->not->toBeNull();

    $dismissedDelivery = $seenDelivery->dismissViaWorkflow();

    expect($dismissedDelivery->status)->toBe(PopupAnnouncementDelivery::STATUS_DISMISSED)
        ->and($dismissedDelivery->dismissed_at)->not->toBeNull();
});

it('prevents dismissing popup deliveries that require acknowledgement', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Doctor');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup workflow ack',
        'message' => 'Ack flow',
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

    $unchangedDelivery = app(PopupAnnouncementDeliveryWorkflowService::class)->dismiss($delivery);

    expect($unchangedDelivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_PENDING)
        ->and($unchangedDelivery->dismissed_at)->toBeNull();
});

it('expires only active popup deliveries for targeted announcements', function (): void {
    $pendingUser = User::factory()->create();
    $pendingUser->assignRole('CSKH');
    $seenUser = User::factory()->create();
    $seenUser->assignRole('CSKH');
    $acknowledgedUser = User::factory()->create();
    $acknowledgedUser->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup workflow expire',
        'message' => 'Expire flow',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'require_ack' => false,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subMinute(),
        'published_at' => now()->subDay(),
    ]);

    $pendingDelivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $pendingUser->id,
        'status' => PopupAnnouncementDelivery::STATUS_PENDING,
        'delivered_at' => now()->subHour(),
    ]);

    $seenDelivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $seenUser->id,
        'status' => PopupAnnouncementDelivery::STATUS_SEEN,
        'delivered_at' => now()->subHour(),
        'seen_at' => now()->subMinutes(30),
    ]);

    $acknowledgedDelivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $acknowledgedUser->id,
        'status' => PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED,
        'delivered_at' => now()->subHour(),
        'acknowledged_at' => now()->subMinutes(10),
    ]);

    $expiredCount = app(PopupAnnouncementDeliveryWorkflowService::class)
        ->expireActiveDeliveriesForAnnouncements([$announcement->id]);

    expect($expiredCount)->toBe(2)
        ->and($pendingDelivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_EXPIRED)
        ->and($pendingDelivery->expired_at)->not->toBeNull()
        ->and($seenDelivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_EXPIRED)
        ->and($seenDelivery->expired_at)->not->toBeNull()
        ->and($acknowledgedDelivery->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED)
        ->and($acknowledgedDelivery->expired_at)->toBeNull();
});
