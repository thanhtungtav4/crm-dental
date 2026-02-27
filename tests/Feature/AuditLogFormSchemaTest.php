<?php

use Illuminate\Support\Facades\File;

it('uses schemas section component in audit log infolist schema', function (): void {
    $path = app_path('Filament/Resources/AuditLogs/Schemas/AuditLogForm.php');
    $content = File::get($path);

    expect($content)
        ->toContain('use Filament\\Schemas\\Components\\Section;')
        ->not->toContain('use Filament\\Infolists\\Components\\Section;');
});
