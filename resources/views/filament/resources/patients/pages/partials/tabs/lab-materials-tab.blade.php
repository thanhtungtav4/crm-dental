@props([
    'record',
    'renderedLabMaterialSections' => [],
])

<div class="crm-pane-stack" wire:key="patient-{{ $record->id }}-lab-materials">
    @foreach($renderedLabMaterialSections as $section)
        @include('filament.resources.patients.pages.partials.feature-table-section', [
            'section' => $section,
        ])
    @endforeach
</div>
