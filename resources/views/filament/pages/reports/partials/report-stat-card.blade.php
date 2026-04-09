@props([
    'card',
])

<div class="{{ $card['container_classes'] }}">
    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $card['value'] }}</div>
    @if(filled($card['description']))
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['description'] }}</div>
    @endif
</div>
