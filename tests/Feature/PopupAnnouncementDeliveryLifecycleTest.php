<?php

use App\Models\AuditLog;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Services\PopupAnnouncementDeliveryWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makePopupDeliveryFixture(bool $requireAck = false, string $status = PopupAnnouncementDelivery::STATUS_PENDING): array
{
    $user = User::factory()->create();
    $user->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Test Popup',
        'message' => 'Test message',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [],
        'require_ack' => $requireAck,
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $delivery = PopupAnnouncementDelivery::query()->create([
        'popup_announcement_id' => $announcement->id,
        'user_id' => $user->id,
        'status' => $status,
        'delivered_at' => now()->subMinute(),
        'display_count' => 0,
    ]);

    return [$announcement, $delivery, $user];
}

function popupDeliveryService(): PopupAnnouncementDeliveryWorkflowService
{
    return app(PopupAnnouncementDeliveryWorkflowService::class);
}

// ---------------------------------------------------------------------------
// markSeen — happy path + audit
// ---------------------------------------------------------------------------

describe('PopupAnnouncementDelivery — markSeen', function (): void {
    it('transitions pending → seen and writes audit with trigger popup_seen', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture();

        $result = popupDeliveryService()->markSeen($delivery);

        expect($result->status)->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
            ->and($result->seen_at)->not->toBeNull()
            ->and((int) $result->display_count)->toBe(1);

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
            ->where('entity_id', $announcement->id)
            ->where('action', AuditLog::ACTION_UPDATE)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('popup_seen')
            ->and($log->metadata['status_from'])->toBe(PopupAnnouncementDelivery::STATUS_PENDING)
            ->and($log->metadata['status_to'])->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
            ->and($log->metadata['delivery_id'])->toBe($delivery->id);
    });

    it('is idempotent — does not write a second audit if already seen', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture(status: PopupAnnouncementDelivery::STATUS_SEEN);
        $delivery->seen_at = now()->subMinute();
        $delivery->save();

        popupDeliveryService()->markSeen($delivery);

        expect(
            AuditLog::query()
                ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
                ->where('entity_id', $announcement->id)
                ->where('action', AuditLog::ACTION_UPDATE)
                ->count()
        )->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// acknowledge — happy path + audit
// ---------------------------------------------------------------------------

describe('PopupAnnouncementDelivery — acknowledge', function (): void {
    it('transitions seen → acknowledged and writes audit with trigger popup_acknowledged', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture(status: PopupAnnouncementDelivery::STATUS_SEEN);

        $result = popupDeliveryService()->acknowledge($delivery);

        expect($result->status)->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED)
            ->and($result->acknowledged_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
            ->where('entity_id', $announcement->id)
            ->where('action', AuditLog::ACTION_APPROVE)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('popup_acknowledged')
            ->and($log->metadata['status_from'])->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
            ->and($log->metadata['status_to'])->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED)
            ->and($log->metadata['delivery_id'])->toBe($delivery->id);
    });
});

// ---------------------------------------------------------------------------
// dismiss — happy path + require_ack guard
// ---------------------------------------------------------------------------

describe('PopupAnnouncementDelivery — dismiss', function (): void {
    it('transitions seen → dismissed and writes audit with trigger popup_dismissed', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture(requireAck: false, status: PopupAnnouncementDelivery::STATUS_SEEN);

        $result = popupDeliveryService()->dismiss($delivery);

        expect($result->status)->toBe(PopupAnnouncementDelivery::STATUS_DISMISSED)
            ->and($result->dismissed_at)->not->toBeNull();

        $log = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
            ->where('entity_id', $announcement->id)
            ->where('action', AuditLog::ACTION_CANCEL)
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->metadata['trigger'])->toBe('popup_dismissed')
            ->and($log->metadata['status_from'])->toBe(PopupAnnouncementDelivery::STATUS_SEEN)
            ->and($log->metadata['status_to'])->toBe(PopupAnnouncementDelivery::STATUS_DISMISSED);
    });

    it('blocks dismiss when require_ack=true and writes no audit', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture(requireAck: true);
        // load announcement relationship so service can check require_ack
        $delivery->load('announcement');

        $result = popupDeliveryService()->dismiss($delivery);

        expect($result->refresh()->status)->toBe(PopupAnnouncementDelivery::STATUS_PENDING);

        expect(
            AuditLog::query()
                ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
                ->where('entity_id', $announcement->id)
                ->where('action', AuditLog::ACTION_CANCEL)
                ->count()
        )->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Full lifecycle — pending → seen → acknowledged
// ---------------------------------------------------------------------------

describe('PopupAnnouncementDelivery — full lifecycle', function (): void {
    it('completes pending → seen → acknowledged lifecycle with two audit entries', function (): void {
        [$announcement, $delivery] = makePopupDeliveryFixture(requireAck: true);

        $seen = popupDeliveryService()->markSeen($delivery);
        $acked = popupDeliveryService()->acknowledge($seen);

        expect($acked->status)->toBe(PopupAnnouncementDelivery::STATUS_ACKNOWLEDGED);

        $logs = AuditLog::query()
            ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
            ->where('entity_id', $announcement->id)
            ->orderBy('id')
            ->get();

        expect($logs)->toHaveCount(2)
            ->and($logs->first()->metadata['trigger'])->toBe('popup_seen')
            ->and($logs->last()->metadata['trigger'])->toBe('popup_acknowledged');
    });
});
