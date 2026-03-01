<?php

use Illuminate\Support\Facades\File;

it('keeps doctor clear buttons vertically centered in patient exam form', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('wire:click="clearExaminingDoctor" class="absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500" style="right: 0.5rem; top: 50%; transform: translateY(-50%);"');
    expect($blade)->toContain('wire:click="clearTreatingDoctor" class="absolute flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:text-red-500" style="right: 0.5rem; top: 50%; transform: translateY(-50%);"');
    expect(substr_count($blade, 'bg-white pr-10 text-sm'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($blade, 'style="padding-right: 2.5rem;"'))->toBeGreaterThanOrEqual(2);
});

it('keeps delete session icon button dimensions stable in patient exam form', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('wire:click="deleteSession({{ $session->id }})"');
    expect($blade)->toContain('style="width: 2rem; height: 2rem; min-width: 2rem; padding: 0;"');
    expect($blade)->toContain('<svg class="h-4 w-4" style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">');
});

it('uses segmented dentition controls for adult and child modes', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('class="crm-dentition-toggle"');
    expect($blade)->toContain('class="crm-dentition-option"');
    expect($blade)->toContain("dentitionMode === 'adult' ? 'is-active' : ''");
    expect($blade)->toContain("dentitionMode === 'child' ? 'is-active' : ''");
    expect($blade)->toContain("dentitionMode === 'adult'");
    expect($blade)->toContain('min-width: 78px; height: 30px; padding: 0 10px;');
    expect($blade)->toContain('background: var(--crm-primary, #7c6cf6); color: #fff;');
    expect($blade)->toContain('Người lớn');
    expect($blade)->toContain('Trẻ em');
});

it('supports explicit multi-select mode in patient exam tooth chart', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('multiSelectMode: false');
    expect($blade)->toContain('toggleMultiSelectMode()');
    expect($blade)->toContain('this.multiSelectMode || (event && (event.ctrlKey || event.metaKey))');
    expect($blade)->toContain('Chọn nhiều');
    expect($blade)->toContain('hỗ trợ mobile');
});

it('renders indication upload areas dynamically per selected indication type', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('$selectedIndicationUploadTypes = collect($indications)')
        ->and($blade)->toContain('@foreach($selectedIndicationUploadTypes as $type)')
        ->and($blade)->toContain('wire:model="tempUploads.{{ $type }}"')
        ->and($blade)->toContain("wire:click=\"removeImage('{{ \$type }}', {{ \$index }})\"")
        ->and($blade)->toContain('@paste.prevent="handleIndicationPaste(@js($type), $event)"')
        ->and($blade)->toContain('@drop.prevent="handleIndicationDrop(@js($type), $event)"')
        ->and($blade)->not->toContain('wire:model="tempUploads.ext"')
        ->and($blade)->not->toContain('wire:model="tempUploads.int"');
});

it('escapes diagnosis condition codes safely in alpine expressions', function (): void {
    $bladePath = resource_path('views/livewire/patient-exam-form.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain(':class="hasCondition(@js((string) $condition->code)) ? \'bg-primary-50\' : \'\'"')
        ->and($blade)->toContain('@click="toggleCondition(@js((string) $condition->code))"')
        ->and($blade)->toContain(':checked="hasCondition(@js((string) $condition->code))"')
        ->and($blade)->not->toContain("@click=\"toggleCondition('{{ \$condition->code }}')\"");
});
