<?php

use Illuminate\Support\Facades\File;

it('builds indication image uploads dynamically for all indication types in clinical notes relation manager', function (): void {
    $php = File::get(app_path('Filament/Resources/Patients/RelationManagers/ClinicalNotesRelationManager.php'));

    expect($php)->toContain('...$this->buildIndicationImageUploadFields(),')
        ->and($php)->toContain("->helperText('Chỉ hiển thị bác sĩ thuộc chi nhánh đang chọn hoặc chi nhánh gốc của bệnh nhân.')")
        ->and($php)->toContain('protected function buildIndicationImageUploadFields(): array')
        ->and($php)->toContain('foreach (ClinicRuntimeSettings::examIndicationOptions() as $key => $label)')
        ->and($php)->toContain('FileUpload::make("indication_images.{$normalizedKey}")')
        ->and($php)->toContain("->helperText('Tải ảnh/X-quang trực tiếp từ máy. Nếu file lớn bị gián đoạn, lưu phiếu rồi tải lại từng ảnh để tránh mất dữ liệu đang nhập.')")
        ->and($php)->toContain('->visible(fn (Get $get): bool => in_array($normalizedKey, (array) ($get(\'indications\') ?? []), true))')
        ->and($php)->not->toContain("FileUpload::make('indication_images.ext')")
        ->and($php)->not->toContain("FileUpload::make('indication_images.int')");
});
