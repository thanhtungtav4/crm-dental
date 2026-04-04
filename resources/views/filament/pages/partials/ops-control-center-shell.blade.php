@props([
    'viewState',
])

<div class="ops-page-shell space-y-6">
    @include('filament.pages.partials.ops-overview-grid-panel', [
        'panel' => $viewState['overview_panel'],
    ])

    <div class="ops-detail-grid">
        @foreach($viewState['detail_columns'] as $column)
            @include('filament.pages.partials.control-plane-section-column', [
                'column' => $column,
            ])
        @endforeach
    </div>
</div>
