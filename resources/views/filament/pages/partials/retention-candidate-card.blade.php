@props([
    'candidate',
    'containerClasses' => 'rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60',
])

<div class="{{ $containerClasses }}">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $candidate['label'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $candidate['description'] }}</p>
        </div>
        <span class="{{ $candidate['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $candidate['badge_label'] }}
        </span>
    </div>
    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $candidate['detail_label'] }}</div>
</div>
