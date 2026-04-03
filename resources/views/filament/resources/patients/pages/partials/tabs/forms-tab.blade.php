@php
    /** @var \App\Models\Patient $record */
    $record = $record;
    /** @var array<string, mixed> $renderedFormsPanel */
    $renderedFormsPanel = $renderedFormsPanel ?? [];
@endphp

<div class="crm-pane-stack" wire:key="patient-{{ $record->id }}-forms">
    <div class="crm-feature-card">
        @include('filament.resources.patients.pages.partials.section-header', [
            'title' => $renderedFormsPanel['title'] ?? '',
            'description' => $renderedFormsPanel['description'] ?? '',
            'action' => null,
        ])
    </div>

    <div class="crm-forms-grid">
        @foreach($renderedFormsPanel['sections'] ?? [] as $section)
            @include('filament.resources.patients.pages.partials.link-list-section', [
                'section' => $section,
            ])
        @endforeach
    </div>
</div>
