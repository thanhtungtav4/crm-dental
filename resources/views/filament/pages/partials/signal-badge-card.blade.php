@php
    $containerClasses = $containerClasses ?? 'rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-950/60';
@endphp

<div class="{{ $containerClasses }}">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $card['label'] }}</p>
        </div>
        <span class="{{ $card['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
            {{ $card['badge_label'] }}
        </span>
    </div>
</div>
