@props([
    'card',
])

<div class="{{ $card['container_classes'] ?? 'rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60' }}">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="{{ $card['label_classes'] ?? 'text-sm font-medium text-gray-500' }}">{{ $card['label'] }}</p>
        </div>
        <span class="{{ $card['value_classes'] ?? 'text-xl font-semibold text-gray-900 dark:text-white' }}">
            {{ $card['value_label'] }}
        </span>
    </div>
</div>
