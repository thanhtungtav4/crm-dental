<?php

use Illuminate\Support\Facades\File;

it('configures popup announcement message as rich editor with file attachments', function (): void {
    $schemaFile = File::get(app_path('Filament/Resources/PopupAnnouncements/Schemas/PopupAnnouncementForm.php'));

    expect($schemaFile)
        ->toContain("Forms\\Components\\RichEditor::make('message')")
        ->toContain('->toolbarButtons([')
        ->toContain("'attachFiles'")
        ->toContain("->fileAttachmentsDisk('public')")
        ->toContain("->fileAttachmentsDirectory('popup-announcements')")
        ->toContain("->fileAttachmentsVisibility('public')");
});

it('renders popup message using rich content renderer', function (): void {
    $viewFile = File::get(resource_path('views/livewire/partials/popup-announcement-dialog.blade.php'));

    expect($viewFile)
        ->toContain('RichContentRenderer::make')
        ->toContain("->fileAttachmentsDisk('public')")
        ->toContain("->fileAttachmentsVisibility('public')")
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('overscroll-contain')
        ->toContain('text-pretty');
});
