<?php

use Illuminate\Support\Facades\File;

it('keeps patient phone chip text white for contrast on blue header', function (): void {
    $bladePath = resource_path('views/filament/resources/patients/pages/partials/patient-overview-card.blade.php');
    $blade = File::get($bladePath);

    expect($blade)
        ->toContain('class="crm-patient-phone-chip" style="color: #ffffff;"')
        ->toContain('class="crm-patient-phone-chip-text" style="color: #ffffff;"');
});

it('keeps lab materials primary actions text white for contrast', function (): void {
    $sectionHeaderBlade = File::get(resource_path('views/filament/resources/patients/pages/partials/section-header.blade.php'));
    $readModelService = File::get(app_path('Services/PatientOverviewReadModelService.php'));

    expect($readModelService)
        ->toContain('Tạo lệnh labo')
        ->toContain('Tạo phiếu xuất')
        ->toContain("'style' => 'color: #ffffff;'");

    expect($sectionHeaderBlade)
        ->toContain('class="crm-btn {{ $action[\'button_class\'] }} crm-btn-md"')
        ->toContain('@if(! empty($action[\'style\'])) style="{{ $action[\'style\'] }}" @endif');
});
