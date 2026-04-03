@php
    $containerClasses = $containerClasses ?? ($card['card_classes'] ?? 'rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/60');
    $labelClasses = $labelClasses ?? ($card['label_classes'] ?? 'text-gray-500');
    $valueClasses = $valueClasses ?? ($card['value_classes'] ?? 'text-gray-900 dark:text-white');
    $valueElementClasses = $valueElementClasses ?? 'text-xl font-semibold';
@endphp

<div class="{{ $containerClasses }}">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="{{ $labelClasses }} text-sm font-medium">{{ $card['label'] }}</p>
        </div>
        <span class="{{ $valueClasses }} {{ $valueElementClasses }}">
            {{ $card['value_label'] }}
        </span>
    </div>
</div>
