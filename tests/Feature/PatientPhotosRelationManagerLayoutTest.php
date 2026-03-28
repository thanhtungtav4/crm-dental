<?php

use Illuminate\Support\Facades\File;

it('sets a visible actions column label for patient photo table', function (): void {
    $phpPath = app_path('Filament/Resources/Patients/RelationManagers/PatientPhotosRelationManager.php');
    $php = File::get($phpPath);

    expect($php)
        ->toContain("->actionsColumnLabel('Thao tác')");
});

it('constrains photos tab actions column width to prevent blank oversized header cell', function (): void {
    $cssPath = resource_path('css/filament/admin/theme.css');
    $css = File::get($cssPath);

    expect($css)
        ->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table th.fi-ta-actions-header-cell')
        ->and($css)->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table td.fi-ta-actions-cell')
        ->and($css)->toContain('width: 116px;')
        ->and($css)->toContain('min-width: 116px;')
        ->and($css)->toContain('.crm-rel-tab.crm-rel-tab-photos .fi-ta-table .fi-ta-actions {')
        ->and($css)->toContain('justify-content: center;');
});

it('adds operator guidance for patient photo and x-ray uploads', function (): void {
    $phpPath = app_path('Filament/Resources/Patients/RelationManagers/PatientPhotosRelationManager.php');
    $php = File::get($phpPath);

    expect($php)
        ->toContain("->helperText('Tải trực tiếp từ máy hoặc dán ảnh nhanh. Nếu mạng chập chờn, nên lưu theo từng nhóm ảnh để tránh phải tải lại toàn bộ.')")
        ->and($php)->toContain("->helperText('Ưu tiên tải từng phim X-quang theo nhóm nhỏ. Nếu file lớn bị gián đoạn, lưu trước các ảnh đã xong rồi tiếp tục bổ sung.')")
        ->and($php)->toContain("->helperText('Có thể tải nhiều ảnh một lần. Nên đặt tiêu đề hoặc mô tả ngắn để dễ rà soát lại sau điều trị.')")
        ->and($php)->toContain("->helperText('Có thể tải nhiều ảnh một lần. Với bộ ảnh lớn, nên chia theo nhóm để giảm rủi ro tải lại khi mất kết nối.')");
});
