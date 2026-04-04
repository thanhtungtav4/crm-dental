@props([
    'column',
])

<div class="{{ $column['column_classes'] }}">
    @include('filament.pages.partials.control-plane-section-list', [
        'sections' => $column['sections'],
    ])
</div>
