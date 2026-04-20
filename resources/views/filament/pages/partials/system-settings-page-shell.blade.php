@props([
    'viewState',
])

<div class="space-y-6">
    @foreach($viewState['sections'] as $section)
        @include('filament.pages.partials.system-settings-section-panel', [
            'section' => $section,
        ])
    @endforeach
</div>
