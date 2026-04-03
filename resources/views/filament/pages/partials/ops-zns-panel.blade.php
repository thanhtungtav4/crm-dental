<div class="space-y-4">
    @include('filament.pages.partials.section-summary-banner', [
        'summary' => $panel['summary'],
    ])

    <div class="ops-grid-2">
        @foreach(($panel['summary_cards'] ?? []) as $card)
            @include('filament.pages.partials.dashboard-summary-card', [
                'card' => $card,
                'containerClasses' => ($card['card_classes'] ?? '').' rounded-xl border px-4 py-3',
                'labelClasses' => ($card['label_classes'] ?? ''),
                'valueClasses' => ($card['value_classes'] ?? ''),
                'valueElementClasses' => 'text-sm font-semibold',
            ])
        @endforeach
    </div>

    <div class="space-y-3">
        @foreach(($panel['retention_candidates'] ?? []) as $candidate)
            @include('filament.pages.partials.retention-candidate-card', [
                'candidate' => $candidate,
            ])
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        @foreach(($panel['links'] ?? []) as $link)
            <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                {{ $link['label'] }}
            </x-filament::link>
        @endforeach
    </div>
</div>
