@props([
    'card',
])

<div class="rounded-2xl border px-4 py-4 shadow-sm {{ $card['card_class'] }}">
    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] {{ $card['label_class'] }}">{{ $card['label'] }}</p>
    <p class="mt-2 text-2xl font-semibold {{ $card['value_class'] }}">{{ $card['count'] }}</p>
    <p class="mt-1 text-xs {{ $card['description_class'] }}">{{ $card['description'] }}</p>
</div>
