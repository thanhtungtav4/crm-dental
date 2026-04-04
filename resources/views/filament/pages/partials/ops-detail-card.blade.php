@props([
    'card',
])

<div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $card['title'] }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['subtitle'] }}</p>
        </div>

        @if(filled($card['badge_label'] ?? null))
            <span class="{{ $card['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ $card['badge_label'] }}
            </span>
        @endif
    </div>

    @if(filled($card['detail'] ?? null))
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $card['detail'] }}</p>
    @endif
</div>
