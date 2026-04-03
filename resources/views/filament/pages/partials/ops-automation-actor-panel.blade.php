<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <span class="{{ $panel['status_badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $panel['status_badge_label'] }}
        </span>
        <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $panel['label'] }}
        </span>
    </div>

    @include('filament.pages.partials.ops-meta-grid', [
        'items' => $panel['meta'] ?? [],
        'gridClasses' => 'ops-grid-3',
        'containerClasses' => 'rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/60',
        'valueClasses' => 'ops-break-words mt-1 text-sm font-medium text-gray-950 dark:text-white',
    ])

    @if(! empty($panel['issues']))
        <div class="space-y-2">
            @foreach($panel['issues'] as $issue)
                <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-900/60 dark:bg-warning-950/30 dark:text-warning-100">
                    <div class="font-semibold">{{ strtoupper($issue['severity']) }} · {{ $issue['code'] }}</div>
                    <div class="mt-1">{{ $issue['message'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
