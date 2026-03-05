<?php

use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\PopupAnnouncement;
use App\Models\PopupAnnouncementDelivery;
use App\Models\User;
use App\Services\PopupAnnouncementDispatchService;
use Illuminate\Validation\ValidationException;

it('dispatches popup announcements to users by role and accessible branch without duplicates', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $sender = User::factory()->create(['branch_id' => $branchA->id]);
    $sender->assignRole('Manager');
    $this->actingAs($sender);

    $recipientA = User::factory()->create(['branch_id' => $branchA->id]);
    $recipientA->assignRole('CSKH');

    $recipientB = User::factory()->create(['branch_id' => $branchB->id]);
    $recipientB->assignRole('CSKH');

    $doctorAtA = User::factory()->create(['branch_id' => $branchA->id]);
    $doctorAtA->assignRole('Doctor');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup cho CSKH chi nhánh A',
        'message' => 'Nội dung test dispatch popup',
        'priority' => PopupAnnouncement::PRIORITY_WARNING,
        'status' => PopupAnnouncement::STATUS_SCHEDULED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [$branchA->id],
        'starts_at' => now()->subMinute(),
    ]);

    $service = app(PopupAnnouncementDispatchService::class);

    $reportFirst = $service->dispatchDueAnnouncements();
    $reportSecond = $service->dispatchDueAnnouncements();

    expect($reportFirst['enabled'])->toBeTrue()
        ->and($reportFirst['deliveries_created'])->toBe(1)
        ->and($reportSecond['deliveries_created'])->toBe(0)
        ->and(PopupAnnouncementDelivery::query()->count())->toBe(1)
        ->and(PopupAnnouncementDelivery::query()->where('user_id', $recipientA->id)->exists())->toBeTrue()
        ->and(PopupAnnouncementDelivery::query()->where('user_id', $recipientB->id)->exists())->toBeFalse()
        ->and(PopupAnnouncementDelivery::query()->where('user_id', $doctorAtA->id)->exists())->toBeFalse()
        ->and($announcement->refresh()->status)->toBe(PopupAnnouncement::STATUS_PUBLISHED);
});

it('blocks non configured sender roles from creating global all-branch popup', function (): void {
    ClinicSetting::setValue('popup.sender_roles', ['Admin', 'Manager'], [
        'group' => 'popup',
        'value_type' => 'json',
    ]);

    $doctor = User::factory()->create();
    $doctor->assignRole('Doctor');
    $this->actingAs($doctor);

    expect(function (): void {
        PopupAnnouncement::query()->create([
            'title' => 'Không được gửi global',
            'message' => 'Doctor không có quyền gửi toàn hệ thống',
            'status' => PopupAnnouncement::STATUS_SCHEDULED,
            'target_role_names' => ['CSKH'],
            'target_branch_ids' => [],
            'starts_at' => now(),
        ]);
    })->toThrow(ValidationException::class);
});

it('trims popup message content before save', function (): void {
    $sender = User::factory()->create();
    $sender->assignRole('Manager');
    $this->actingAs($sender);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Normalize message',
        'message' => "  <p>Dòng 1</p>\n<p>Dòng 2</p>  ",
        'status' => PopupAnnouncement::STATUS_DRAFT,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
    ]);

    expect($announcement->message)->toBe("<p>Dòng 1</p>\n<p>Dòng 2</p>");
});

it('marks popup as failed when there are no eligible recipients', function (): void {
    ClinicSetting::setValue('popup.enabled', true, [
        'group' => 'popup',
        'value_type' => 'boolean',
    ]);

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $sender = User::factory()->create(['branch_id' => $branchA->id]);
    $sender->assignRole('Manager');
    $this->actingAs($sender);

    $recipientAtOtherBranch = User::factory()->create(['branch_id' => $branchB->id]);
    $recipientAtOtherBranch->assignRole('CSKH');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup không có người nhận hợp lệ',
        'message' => 'Nội dung test no recipient',
        'priority' => PopupAnnouncement::PRIORITY_INFO,
        'status' => PopupAnnouncement::STATUS_SCHEDULED,
        'target_role_names' => ['CSKH'],
        'target_branch_ids' => [$branchA->id],
        'starts_at' => now()->subMinute(),
    ]);

    $service = app(PopupAnnouncementDispatchService::class);
    $report = $service->dispatchDueAnnouncements();

    expect($report['deliveries_created'])->toBe(0)
        ->and($announcement->refresh()->status)->toBe(PopupAnnouncement::STATUS_FAILED_NO_RECIPIENT)
        ->and(PopupAnnouncementDelivery::query()->count())->toBe(0);
});
