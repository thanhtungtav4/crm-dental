<div class="space-y-4">
    @include('filament.pages.partials.section-summary-banner', [
        'summary' => $panel['summary'],
    ])

    <div class="space-y-3">
        @foreach(($panel['metric_cards'] ?? []) as $metric)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $metric['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $metric['key'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $metric['value_label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $metric['budget_label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Error budget breaches</p>
            <span class="{{ $panel['summary']['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ count($panel['breach_cards'] ?? []) }}
            </span>
        </div>

        @if(($panel['breach_cards'] ?? []) === [])
            @include('filament.pages.partials.ops-empty-state', [
                'message' => 'Không có observability breach nào trong cửa sổ hiện tại.',
            ])
        @else
            <div class="ops-grid-2">
                @foreach(($panel['breach_cards'] ?? []) as $breach)
                    @include('filament.pages.partials.signal-badge-card', [
                        'card' => $breach,
                    ])
                @endforeach
            </div>
        @endif
    </div>

    @if(($panel['missing_runbook_panel']['is_visible'] ?? false) === true)
        <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-4 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100">
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold">{{ $panel['missing_runbook_panel']['heading'] }}</div>
                <span class="{{ $panel['missing_runbook_panel']['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                    {{ $panel['missing_runbook_panel']['badge_label'] }}
                </span>
            </div>

            <div class="mt-2 flex flex-wrap gap-2">
                @foreach(($panel['missing_runbook_panel']['items'] ?? []) as $category)
                    <span class="rounded-full border border-warning-300 px-2.5 py-1 text-xs font-semibold dark:border-warning-800">{{ $category }}</span>
                @endforeach
            </div>
        </div>
    @endif
</div>
