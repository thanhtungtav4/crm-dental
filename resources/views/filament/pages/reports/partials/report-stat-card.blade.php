@props([
    'card',
])

<article id="{{ $card['id'] }}" class="{{ $card['container_classes'] }}" role="listitem" aria-label="{{ $card['aria_label'] }}">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-500/80 via-info-500/70 to-success-500/70 opacity-80"></div>

    <div class="relative space-y-2">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
        <div class="text-2xl font-semibold leading-tight text-gray-950 tabular-nums dark:text-white sm:text-3xl">{{ $card['value'] }}</div>
        @if(filled($card['description']))
            <div class="text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $card['description'] }}</div>
        @endif
    </div>
</article>
