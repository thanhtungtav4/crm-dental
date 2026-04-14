<?php

use App\Models\AuditLog;
use App\Models\PopupAnnouncement;
use App\Models\User;
use App\Services\PopupAnnouncementWorkflowService;

it('records structured audit when popup is cancelled through workflow service', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup huy tay',
        'message' => 'Noi dung popup huy',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $cancelled = app(PopupAnnouncementWorkflowService::class)->cancel(
        announcement: $announcement,
        reason: 'operator_cancel',
    );

    expect($cancelled->status)->toBe(PopupAnnouncement::STATUS_CANCELLED);

    $audit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
        ->where('entity_id', $announcement->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->metadata)->toMatchArray([
            'status_from' => PopupAnnouncement::STATUS_PUBLISHED,
            'status_to' => PopupAnnouncement::STATUS_CANCELLED,
            'reason' => 'operator_cancel',
            'trigger' => 'manual_cancel',
        ]);
});

it('cancels popup announcements through the canonical model workflow method', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup cancel qua model',
        'message' => 'Noi dung popup cancel qua model',
        'status' => PopupAnnouncement::STATUS_PUBLISHED,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
        'starts_at' => now()->subMinute(),
        'published_at' => now()->subMinute(),
    ]);

    $announcement->cancel('model_cancel');

    $cancelAudit = AuditLog::query()
        ->where('entity_type', AuditLog::ENTITY_POPUP_ANNOUNCEMENT)
        ->where('entity_id', $announcement->id)
        ->where('action', AuditLog::ACTION_CANCEL)
        ->latest('id')
        ->first();

    expect($announcement->fresh()->status)->toBe(PopupAnnouncement::STATUS_CANCELLED)
        ->and(data_get($cancelAudit, 'metadata.status_to'))->toBe(PopupAnnouncement::STATUS_CANCELLED)
        ->and(data_get($cancelAudit, 'metadata.reason'))->toBe('model_cancel');
});
