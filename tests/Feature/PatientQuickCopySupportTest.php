<?php

use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\File;

it('auto enables copyable for patient code and phone columns and entries', function (): void {
    $patientCodeColumn = TextColumn::make('patient.patient_code');
    $phoneColumn = TextColumn::make('patient.phone');
    $statusColumn = TextColumn::make('status');

    $patientCodeEntry = TextEntry::make('invoice.patient.patient_code');
    $phoneEntry = TextEntry::make('invoice.patient.phone');
    $noteEntry = TextEntry::make('note');

    expect($patientCodeColumn->isCopyable('PAT-20260303-ABC123'))->toBeTrue()
        ->and($phoneColumn->isCopyable('0909123456'))->toBeTrue()
        ->and($statusColumn->isCopyable('draft'))->toBeFalse()
        ->and($patientCodeColumn->getCopyMessage('PAT-20260303-ABC123'))->toBe('Đã sao chép')
        ->and($patientCodeColumn->getCopyMessageDuration('PAT-20260303-ABC123'))->toBe(1200)
        ->and($patientCodeEntry->isCopyable('PAT-20260303-ABC123'))->toBeTrue()
        ->and($phoneEntry->isCopyable('0909123456'))->toBeTrue()
        ->and($noteEntry->isCopyable('ghi chú'))->toBeFalse()
        ->and($phoneEntry->getCopyMessage('0909123456'))->toBe('Đã sao chép');
});

it('renders quick copy controls on patient profile header and phone card', function (): void {
    $bladePath = resource_path('views/filament/resources/patients/pages/view-patient.blade.php');
    $cssPath = resource_path('css/filament/admin/theme.css');

    $blade = File::get($bladePath);
    $css = File::get($cssPath);

    expect($blade)
        ->toContain("copyToClipboard(@js(\$this->record->patient_code), 'Mã bệnh nhân')")
        ->toContain("copyToClipboard(@js(\$this->record->phone), 'Số điện thoại')")
        ->toContain('class="crm-copy-toast"')
        ->toContain('class="crm-copy-icon-btn is-light"');

    expect($css)
        ->toContain('.crm-copy-icon-btn {')
        ->toContain('.crm-copy-icon-btn.is-light {')
        ->toContain('.crm-copy-toast {');
});
