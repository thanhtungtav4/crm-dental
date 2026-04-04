@props([
    'card',
])

<div class="rounded-xl border p-3 {{ $card['card_class'] }}">
    <p class="text-xs {{ $card['label_class'] }}">{{ $card['label'] }}</p>
    <p class="text-xl font-semibold {{ $card['value_class'] }}">{{ $card['count_label'] }}</p>
</div>
