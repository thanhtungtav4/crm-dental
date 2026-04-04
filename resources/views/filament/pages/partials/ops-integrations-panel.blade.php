@props([
    'panel',
])

<div class="space-y-4">
    @include('filament.pages.partials.section-summary-banner', [
        'summary' => $panel['summary'],
    ])

    @include('filament.pages.partials.ops-meta-grid', [
        'items' => $panel['meta'],
        'gridClasses' => 'ops-grid-3',
        'valueClasses' => 'ops-break-words mt-1 text-sm font-medium text-gray-950 dark:text-white',
    ])

    @include('filament.pages.partials.provider-health-panel', [
        'panel' => $panel['provider_health'],
    ])

    <div class="ops-grid-2">
        @include('filament.pages.partials.grace-rotation-panel', [
            'panel' => $panel['active_grace'],
        ])

        @include('filament.pages.partials.grace-rotation-panel', [
            'panel' => $panel['expired_grace'],
        ])
    </div>

    <div class="space-y-3">
        @foreach($panel['retention_candidates'] as $candidate)
            @include('filament.pages.partials.retention-candidate-card', [
                'candidate' => $candidate,
            ])
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        @foreach($panel['links'] as $link)
            <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                {{ $link['label'] }}
            </x-filament::link>
        @endforeach
    </div>
</div>
