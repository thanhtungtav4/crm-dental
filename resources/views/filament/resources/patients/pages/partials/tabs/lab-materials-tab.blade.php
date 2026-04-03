@php
    /** @var \App\Models\Patient $record */
    $record = $record;
    /** @var array<int, array<string, mixed>> $renderedLabMaterialSections */
    $renderedLabMaterialSections = $renderedLabMaterialSections ?? [];
@endphp

<div class="crm-pane-stack" wire:key="patient-{{ $record->id }}-lab-materials">
    @foreach($renderedLabMaterialSections as $section)
        @include('filament.resources.patients.pages.partials.feature-table-section', [
            'section' => $section,
        ])
    @endforeach
</div>
