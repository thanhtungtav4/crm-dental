<?php

use Illuminate\Support\Facades\File;

it('uses slide overs for popup announcement create edit and view actions', function (): void {
    $listPage = File::get(app_path('Filament/Resources/PopupAnnouncements/Pages/ListPopupAnnouncements.php'));
    $table = File::get(app_path('Filament/Resources/PopupAnnouncements/Tables/PopupAnnouncementsTable.php'));

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
        ->toContain("->helperText('Bật khi người nhận phải bấm “Tôi đã đọc” trước khi đóng popup.')");

    expect($infolist)
        ->toContain("TextEntry::make('message')")
        ->toContain('->html()')
        ->toContain('->prose()');
});
