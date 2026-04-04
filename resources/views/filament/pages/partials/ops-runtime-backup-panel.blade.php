<div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $panel['label'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
        </div>
        <span class="{{ $panel['status_badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $panel['status_badge_label'] }}
        </span>
    </div>

    @include('filament.pages.partials.ops-meta-grid', [
        'items' => $panel['meta'] ?? [],
        'gridClasses' => 'ops-grid-3 mt-3',
        'containerClasses' => 'rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60',
        'valueClasses' => 'ops-break-words mt-1 text-sm font-medium text-gray-950 dark:text-white',
    ])

    <div class="ops-break-words mt-3 rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
        {{ $panel['path'] }}
        @if(filled($panel['error'] ?? null))
            <div class="mt-1 font-medium text-danger-700 dark:text-danger-300">{{ $panel['error'] }}</div>
        @endif
    </div>
</div>
