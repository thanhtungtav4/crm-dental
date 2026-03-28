<?php

use Illuminate\Support\Facades\File;

it('uses schemas section component in audit log infolist schema', function (): void {
    $path = app_path('Filament/Resources/AuditLogs/Schemas/AuditLogForm.php');
    $content = File::get($path);

    expect($content)
        ->toContain('use Filament\\Schemas\\Components\\Section;')
        ->not->toContain('use Filament\\Infolists\\Components\\Section;');
});

it('includes popup announcement entity in audit log table filters and badges', function (): void {
    $path = app_path('Filament/Resources/AuditLogs/Tables/AuditLogsTable.php');
    $content = File::get($path);

    expect($content)
        ->toContain('AuditLog::ENTITY_POPUP_ANNOUNCEMENT')
        ->toContain("'Popup Announcement'");
});

it('includes receipt expense entity in audit log table filters and badges', function (): void {
    $path = app_path('Filament/Resources/AuditLogs/Tables/AuditLogsTable.php');
    $content = File::get($path);

    expect($content)
        ->toContain('AuditLog::ENTITY_RECEIPT_EXPENSE')
        ->toContain("'Receipt Expense'");
});

it('includes material issue note entity in audit log table filters and badges', function (): void {
    $path = app_path('Filament/Resources/AuditLogs/Tables/AuditLogsTable.php');
    $content = File::get($path);

    expect($content)
        ->toContain('AuditLog::ENTITY_MATERIAL_ISSUE_NOTE')
        ->toContain("'Material Issue Note'");
});
