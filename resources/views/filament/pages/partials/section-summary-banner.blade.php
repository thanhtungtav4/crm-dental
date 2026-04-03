@php
    $containerClasses = $containerClasses ?? 'flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60';
@endphp

<div class="{{ $containerClasses }}">
    <div>
        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $summary['title'] }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $summary['description'] }}</p>
    </div>
    <span class="{{ $summary['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
        {{ $summary['badge_label'] }}
    </span>
</div>
