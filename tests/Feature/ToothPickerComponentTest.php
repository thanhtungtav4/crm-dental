<?php

use Illuminate\Support\Facades\File;

it('renders child dentition picker instead of placeholder message', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->not->toContain('Chức năng chọn răng sữa đang được cập nhật...');
    expect($blade)->toContain('Răng sữa hàm trên');
    expect($blade)->toContain('Răng sữa hàm dưới');
    expect($blade)->toContain('crm-tooth-picker-grid-10');
    expect($blade)->toContain('grid-template-columns: repeat(10, minmax(0, 1fr));');
    expect($blade)->toContain('@foreach($childTeeth[\'upper\'] as $t)');
    expect($blade)->toContain('@foreach($childTeeth[\'lower\'] as $t)');
});

it('uses draft state and explicit cancel semantics in tooth picker modal', function (): void {
    $bladePath = resource_path('views/filament/forms/components/tooth-picker.blade.php');
    $blade = File::get($bladePath);

    expect($blade)->toContain('draftState: []');
    expect($blade)->toContain('cancelPicker()');
    expect($blade)->toContain('confirmPicker()');
    expect($blade)->toContain('@click.self="cancelPicker()"');
    expect($blade)->toContain('@keydown.escape.window="if (modalOpen) { cancelPicker(); }"');
    expect($blade)->toContain('@click="confirmPicker()"');
});
