<?php

use App\Models\PopupAnnouncement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('removes destructive actions from popup announcement resource surfaces', function (): void {
    $editPage = file_get_contents(app_path('Filament/Resources/PopupAnnouncements/Pages/EditPopupAnnouncement.php'));
    $table = file_get_contents(app_path('Filament/Resources/PopupAnnouncements/Tables/PopupAnnouncementsTable.php'));

    expect($editPage)
        ->not->toContain('DeleteAction::make()')
        ->not->toContain('ForceDeleteAction::make()')
        ->not->toContain('RestoreAction::make()');

    expect($table)
        ->not->toContain('DeleteBulkAction::make()')
        ->not->toContain('ForceDeleteBulkAction::make()')
        ->not->toContain('RestoreBulkAction::make()');
});

it('denies delete restore and force delete popup announcement abilities via policy', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup bi khoa xoa',
        'message' => 'Noi dung popup bi khoa xoa',
        'status' => PopupAnnouncement::STATUS_DRAFT,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
    ]);

    expect($manager->can('delete', $announcement))->toBeFalse()
        ->and($manager->can('deleteAny', PopupAnnouncement::class))->toBeFalse()
        ->and($manager->can('restore', $announcement))->toBeFalse()
        ->and($manager->can('restoreAny', PopupAnnouncement::class))->toBeFalse()
        ->and($manager->can('forceDelete', $announcement))->toBeFalse()
        ->and($manager->can('forceDeleteAny', PopupAnnouncement::class))->toBeFalse();
});

it('blocks direct popup announcement delete attempts at model layer', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $this->actingAs($manager);

    $announcement = PopupAnnouncement::query()->create([
        'title' => 'Popup khong xoa truc tiep',
        'message' => 'Noi dung popup',
        'status' => PopupAnnouncement::STATUS_DRAFT,
        'target_role_names' => ['Manager'],
        'target_branch_ids' => [],
    ]);

    expect(fn () => $announcement->delete())
        ->toThrow(ValidationException::class, 'không hỗ trợ xóa trực tiếp');
});
