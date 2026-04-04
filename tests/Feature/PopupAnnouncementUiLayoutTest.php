<?php

use Illuminate\Support\Facades\File;

it('uses slide overs for popup announcement create edit and view actions', function (): void {
    $listPage = File::get(app_path('Filament/Resources/PopupAnnouncements/Pages/ListPopupAnnouncements.php'));
    $table = File::get(app_path('Filament/Resources/PopupAnnouncements/Tables/PopupAnnouncementsTable.php'));
    $editPage = File::get(app_path('Filament/Resources/PopupAnnouncements/Pages/EditPopupAnnouncement.php'));

    expect($listPage)
        ->toContain('CreateAction::make()')
        ->toContain('->slideOver()')
        ->toContain("->modalWidth('6xl')")
        ->toContain("->modalHeading('Tạo popup nội bộ')");

    expect($table)
        ->toContain('ViewAction::make()')
        ->toContain('EditAction::make()')
        ->toContain("->modalWidth('5xl')")
        ->toContain("->modalWidth('6xl')")
        ->toContain('->modalHeading(\'Phát popup ngay bây giờ?\')')
        ->toContain('->modalHeading(\'Hủy popup này?\')');

    expect($editPage)
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('PopupAnnouncementWorkflowService::class')
        ->toContain("Action::make('publishNow')")
        ->toContain("Action::make('cancel')");
});

it('organizes popup announcement form into operator friendly sections', function (): void {
    $form = File::get(app_path('Filament/Resources/PopupAnnouncements/Schemas/PopupAnnouncementForm.php'));
    $infolist = File::get(app_path('Filament/Resources/PopupAnnouncements/Schemas/PopupAnnouncementInfolist.php'));

    expect($form)
        ->toContain("Section::make('Nội dung popup')")
        ->toContain("Section::make('Lịch phát & mức độ')")
        ->toContain("Section::make('Đối tượng nhận')")
        ->toContain("Section::make('Quy tắc hiển thị')")
        ->toContain('->columns(3)')
        ->toContain('->columns(2)')
        ->toContain('->disabled(fn (?Model $record): bool => $record !== null)')
        ->toContain("->helperText('Bật khi người nhận phải bấm “Tôi đã đọc” trước khi đóng popup.')");

    expect($infolist)
        ->toContain("TextEntry::make('message')")
        ->toContain('->html()')
        ->toContain('->prose()');
});

it('renders popup center badges and close action from payload state', function (): void {
    $popupCenterView = File::get(resource_path('views/livewire/popup-announcement-center.blade.php'));
    $shellPartial = File::get(resource_path('views/livewire/partials/popup-announcement-center-shell.blade.php'));
    $dialogPartial = File::get(resource_path('views/livewire/partials/popup-announcement-dialog.blade.php'));

    expect($popupCenterView)
        ->not->toContain('@php($viewState = $this->viewState)')
        ->toContain("@include('livewire.partials.popup-announcement-center-shell'")
        ->toContain("'viewState' => \$this->viewState");

    expect($shellPartial)
        ->toContain("wire:poll.visible.{{ \$viewState['polling_interval'] }}s=\"refreshPending\"")
        ->toContain("aria-live=\"{{ \$viewState['aria_live'] }}\"")
        ->toContain("@if(\$viewState['has_announcement'])")
        ->toContain("@include('livewire.partials.popup-announcement-dialog'");

    expect($dialogPartial)
        ->toContain("\$announcement['mode_classes']")
        ->toContain("\$announcement['dialog_aria_describedby']")
        ->toContain("\$announcement['close_action']['wire_click']")
        ->toContain("\$announcement['close_action']['wire_target']")
        ->toContain("\$announcement['primary_action']['color']")
        ->toContain("\$announcement['primary_action']['wire_click']")
        ->toContain("\$announcement['primary_action']['wire_target']");
});
