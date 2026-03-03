<?php

use Illuminate\Support\Facades\File;

it('keeps patient phone chip text white for contrast on blue header', function (): void {
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');
    $blade = File::get($bladePath);

    expect($blade)
        ->toContain('class="crm-patient-phone-chip" style="color: #ffffff;"')
        ->toContain('class="crm-patient-phone-chip-text" style="color: #ffffff;"');
});

it('keeps lab materials primary actions text white for contrast', function (): void {
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');
    $blade = File::get($bladePath);

    expect($blade)
        ->toContain('Tạo lệnh labo')
        ->toContain('Tạo phiếu xuất')
        ->toContain('class="crm-btn crm-btn-primary crm-btn-md"')
        ->toContain('style="color: #ffffff;"');
});
