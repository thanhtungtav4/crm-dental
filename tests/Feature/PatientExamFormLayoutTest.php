<?php

use Illuminate\Support\Facades\File;

function patientExamBladeFiles(): array
{
    return [
        resource_path('views/livewire/patient-exam-form.blade.php'),
        resource_path('views/livewire/partials/patient-exam/general-section.blade.php'),
        resource_path('views/livewire/partials/patient-exam/indications-section.blade.php'),
        resource_path('views/livewire/partials/patient-exam/diagnosis-section.blade.php'),
        resource_path('views/livewire/partials/patient-exam/workspace-state.blade.php'),
    ];
}

function patientExamBladeContent(): string
{
    return implode(PHP_EOL, array_map(
        static fn (string $path): string => File::get($path),
        patientExamBladeFiles(),
    ));
}

it('splits the patient exam form into focused partials', function (): void {
    $blade = File::get(resource_path('views/livewire/patient-exam-form.blade.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain('@foreach($sessionCards as $sessionCard)')
        ->toContain("@include('livewire.partials.patient-exam.general-section')")
        ->toContain("@include('livewire.partials.patient-exam.indications-section')")
        ->toContain("@include('livewire.partials.patient-exam.diagnosis-section')")
        ->toContain("x-data=\"@include('livewire.partials.patient-exam.workspace-state')\"");
});

it('keeps doctor clear buttons vertically centered in patient exam form', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('wire:click="clearExaminingDoctor" class="crm-doctor-clear-btn absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500"');
    expect($blade)->toContain('wire:click="clearTreatingDoctor" class="crm-doctor-clear-btn absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500"');
    expect(substr_count($blade, 'class="crm-doctor-input'))->toBeGreaterThanOrEqual(2);
    expect($blade)->not->toContain('style="padding-right: 2.5rem;"');
});

it('keeps delete session icon button dimensions stable in patient exam form', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('wire:click="deleteSession({{ $sessionCard[\'id\'] }})"');
    expect($blade)->toContain('class="crm-btn crm-btn-outline crm-btn-icon text-gray-600 disabled:opacity-50"');
    expect($blade)->toContain('<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
});

it('uses segmented dentition controls for adult and child modes', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('class="crm-dentition-toggle"');
    expect($blade)->toContain('class="crm-dentition-option"');
    expect($blade)->toContain("dentitionMode === 'adult' ? 'is-active' : ''");
    expect($blade)->toContain("dentitionMode === 'child' ? 'is-active' : ''");
    expect($blade)->toContain("dentitionMode === 'adult'");
    expect($blade)->not->toContain(":style=\"dentitionMode === 'adult'");
    expect($blade)->not->toContain(":style=\"dentitionMode === 'child'");
    expect($blade)->toContain('Người lớn');
    expect($blade)->toContain('Trẻ em');
});

it('offers a quick-create exam session bar with date input and autosave feedback', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)
        ->toContain('wire:model="newSessionDate"')
        ->toContain('Ngày khám mới')
        ->toContain('Phiếu khám tự động lưu sau mỗi thay đổi.')
        ->toContain('wire:loading.delay.flex')
        ->toContain('Đã lưu tự động')
        ->toContain('Đang đồng bộ dữ liệu...');
});

it('supports explicit multi-select mode in patient exam tooth chart', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('multiSelectMode: false');
    expect($blade)->toContain('toggleMultiSelectMode()');
    expect($blade)->toContain('this.multiSelectMode || (event && (event.ctrlKey || event.metaKey))');
    expect($blade)->toContain('Chọn nhiều');
    expect($blade)->toContain('hỗ trợ mobile');
});

it('renders indication upload areas dynamically per selected indication type', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('@foreach($selectedIndicationUploadTypes as $type)')
        ->and($blade)->toContain("wire:key=\"indication-upload-{{ \$sessionCard['id'] }}-{{ \$type }}\"")
        ->and($blade)->toContain('wire:model="tempUploads.{{ $type }}"')
        ->and($blade)->toContain("wire:click=\"removeImage('{{ \$type }}', {{ \$index }})\"")
        ->and($blade)->toContain('@paste.prevent="handleIndicationPaste(@js($type), $event)"')
        ->and($blade)->toContain('@drop.prevent="handleIndicationDrop(@js($type), $event)"')
        ->and($blade)->toContain('class="crm-upload-dropzone')
        ->and($blade)->toContain('wire:loading.flex wire:target="tempUploads.{{ $type }}"')
        ->and($blade)->not->toContain('wire:model="tempUploads.ext"')
        ->and($blade)->not->toContain('wire:model="tempUploads.int"');
});

it('escapes diagnosis condition codes safely in alpine expressions', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain(':class="hasCondition(@js((string) $condition->code)) ? \'bg-primary-50 dark:bg-primary-500/15\' : \'\'"')
        ->and($blade)->toContain('@click="toggleCondition(@js((string) $condition->code))"')
        ->and($blade)->toContain(':checked="hasCondition(@js((string) $condition->code))"')
        ->and($blade)->not->toContain("@click=\"toggleCondition('{{ \$condition->code }}')\"");
});

it('uses dark-mode friendly diagnosis tags and modal controls in patient exam form', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('dark:bg-primary-500/15 dark:text-primary-100')
        ->and($blade)->toContain('class="crm-modal-close-btn"')
        ->and($blade)->toContain('dark:border-gray-700 dark:bg-gray-900')
        ->and($blade)->toContain('dark:hover:bg-gray-800');
});
