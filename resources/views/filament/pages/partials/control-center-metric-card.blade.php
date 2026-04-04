@props([
    'metric',
])

<div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
        </div>
        <span class="{{ $metric['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $metric['label'] }}
        </span>
    </div>
    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $metric['description'] }}</p>
</div>
