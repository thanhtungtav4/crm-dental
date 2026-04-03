<div class="space-y-4">
    @include('filament.pages.partials.section-summary-banner', [
        'summary' => $panel['summary'],
    ])

    @include('filament.pages.partials.ops-meta-grid', [
        'items' => $panel['meta'] ?? [],
        'gridClasses' => 'ops-grid-3',
    ])

    <div class="ops-grid-2">
        @foreach(($panel['snapshot_count_cards'] ?? []) as $card)
            @include('filament.pages.partials.signal-badge-card', [
                'card' => $card,
            ])
        @endforeach
    </div>

    <div class="ops-grid-2">
        @foreach(($panel['aggregate_readiness_cards'] ?? []) as $card)
            @include('filament.pages.partials.signal-badge-card', [
                'card' => $card,
            ])
        @endforeach
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $panel['open_alert_panel']['heading'] }}</p>
            <span class="{{ $panel['open_alert_panel']['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ $panel['open_alert_panel']['badge_label'] }}
            </span>
        </div>

        @forelse(($panel['open_alert_panel']['cards'] ?? []) as $card)
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $card['title'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['meta_text'] }}</p>
                    </div>
                    <span class="{{ $card['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                        {{ $card['badge_label'] }}
                    </span>
                </div>
            </div>
        @empty
            @include('filament.pages.partials.ops-empty-state', [
                'message' => $panel['open_alert_panel']['empty_state_message'],
            ])
        @endforelse
    </div>

    <div class="flex flex-wrap gap-3">
        @foreach(($panel['links'] ?? []) as $link)
            <x-filament::link :href="$link['url']" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                {{ $link['label'] }}
            </x-filament::link>
        @endforeach
    </div>
</div>
