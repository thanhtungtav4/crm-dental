@props([
    'viewState',
])

<div class="space-y-6">
    @include('filament.pages.partials.control-center-overview-grid', [
        'panel' => $viewState['overview_panel'],
    ])

    @include('filament.pages.partials.control-center-quick-links-panel', [
        'panel' => $viewState['quick_links_panel'],
    ])

    <div class="space-y-6">
        @foreach($viewState['sections_panel']['sections'] as $section)
            @include('filament.pages.partials.control-center-section-panel', [
                'section' => $section,
            ])
        @endforeach
    </div>
</div>
