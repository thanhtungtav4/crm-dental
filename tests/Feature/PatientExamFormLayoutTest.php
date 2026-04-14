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
        resource_path('views/filament/forms/components/partials/tooth-chart-rows.blade.php'),
        resource_path('views/filament/forms/components/partials/tooth-chart-legend.blade.php'),
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
    $component = File::get(app_path('Livewire/PatientExamForm.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain('@foreach($sessionCards as $sessionCard)')
        ->toContain("@include('livewire.partials.patient-exam.general-section')")
        ->toContain("@include('livewire.partials.patient-exam.indications-section')")
        ->toContain("@include('livewire.partials.patient-exam.diagnosis-section')")
        ->toContain("x-data=\"@include('livewire.partials.patient-exam.workspace-state')\"");

    expect($component)
        ->toContain("return view('livewire.patient-exam-form', \$this->formViewState(")
        ->toContain('protected function formViewState(')
        ->toContain('protected function sessionPanelData(Collection $sessions): array')
        ->toContain('protected function diagnosisViewData(')
        ->toContain('protected function mediaViewData(array $mediaReadModel): array');
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
    expect($blade)->toContain('$sessionCard[\'delete_action_title\']');
    expect($blade)->toContain('$sessionCard[\'delete_action_label\']');
    expect($blade)->not->toContain("\$sessionCard['is_locked'] ? 'Ngày khám đã có tiến trình điều trị nên không thể xóa được.'");
    expect($blade)->not->toContain("\$sessionCard['is_locked'] ? 'Ngày khám đã khóa, không thể xóa'");
});

it('uses segmented dentition controls for adult and child modes', function (): void {
    $blade = patientExamBladeContent();
    $component = File::get(app_path('Support/ToothChartViewConfig.php'));

    expect($blade)->toContain('class="crm-dentition-toggle"');
    expect($blade)->toContain('class="crm-dentition-option"');
    expect($blade)->toContain('@foreach($diagnosisDentitionOptions as $option)');
    expect($blade)->toContain("dentitionMode === '{{ \$option['mode'] }}' ? 'is-active' : ''");
    expect($blade)->toContain('$diagnosisSelectionHint');
    expect($blade)->not->toContain(":style=\"dentitionMode === 'adult'");
    expect($blade)->not->toContain(":style=\"dentitionMode === 'child'");
    expect($component)->toContain("'Người lớn'");
    expect($component)->toContain("'Trẻ em'");
});

it('offers a quick-create exam session bar with date input and autosave feedback', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)
        ->toContain('wire:model="newSessionDate"')
        ->toContain('Ngày khám mới')
        ->toContain('$activeSessionBadgeLabel')
        ->toContain('Phiếu khám tự động lưu sau mỗi thay đổi.')
        ->toContain('wire:loading.delay.flex')
        ->toContain('Đã lưu tự động')
        ->toContain('Đang đồng bộ dữ liệu...')
        ->not->toContain("session_date?->format('d/m/Y')");
});

it('supports explicit multi-select mode in patient exam tooth chart', function (): void {
    $blade = patientExamBladeContent();

    expect($blade)->toContain('multiSelectMode: false');
    expect($blade)->toContain('toggleMultiSelectMode()');
    expect($blade)->toContain('this.multiSelectMode || (event && (event.ctrlKey || event.metaKey))');
    expect($blade)->toContain('Chọn nhiều');
    expect($blade)->toContain('$diagnosisSelectionHint');
});

it('renders indication upload areas dynamically per selected indication type', function (): void {
    $blade = patientExamBladeContent();
    $component = File::get(app_path('Livewire/PatientExamForm.php'));

    expect($blade)->toContain('@foreach ($indicationOptions as $option)')
        ->and($blade)->toContain('@foreach($indicationUploadCards as $card)')
        ->and($blade)->toContain("wire:key=\"indication-upload-{{ \$sessionCard['id'] }}-{{ \$card['type'] }}\"")
        ->and($blade)->toContain('wire:model="tempUploads.{{ $card[\'type\'] }}"')
        ->and($blade)->toContain("wire:click=\"removeImage('{{ \$card['type'] }}', {{ \$index }})\"")
        ->and($blade)->toContain('@paste.prevent="handleIndicationPaste(@js($card[\'type\']), $event)"')
        ->and($blade)->toContain('@drop.prevent="handleIndicationDrop(@js($card[\'type\']), $event)"')
        ->and($blade)->toContain('class="crm-upload-dropzone')
        ->and($blade)->toContain('wire:loading.flex wire:target="tempUploads.{{ $card[\'type\'] }}"')
        ->and($blade)->toContain('$selectedIndicationUploadCountLabel')
        ->and($blade)->toContain('$evidenceSummaryLabel')
        ->and($blade)->toContain('$evidenceCompletionPercent')
        ->and($blade)->toContain('$evidenceMissingLabel')
        ->and($blade)->toContain('$evidenceQualityWarnings')
        ->and($blade)->toContain('$mediaTimelinePreview')
        ->and($blade)->not->toContain('wire:model="tempUploads.ext"')
        ->and($blade)->not->toContain('wire:model="tempUploads.int"')
        ->and($component)->toContain('protected function indicationsViewData(array $mediaReadModel): array')
        ->and($component)->toContain('protected function indicationOptions(): array')
        ->and($component)->toContain('protected function indicationUploadCards(array $selectedUploadTypes): array');
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
        ->and($blade)->toContain('dark:hover:bg-gray-800')
        ->and($blade)->toContain("@include('filament.forms.components.partials.tooth-chart-legend', ['legendItems' => \$diagnosisTreatmentLegend])")
        ->and($blade)->toContain("@include('filament.forms.components.partials.tooth-chart-rows', ['rows' => \$diagnosisToothRows])")
        ->and($blade)->toContain('@foreach($legendItems as $legendItem)')
        ->and($blade)->toContain('@foreach($rows as $row)');
});
