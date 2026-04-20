@props([
    'cards',
])

<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
    @foreach($cards as $card)
        <div class="{{ $card['container_classes'] }}">
            <p class="{{ $card['label_classes'] }}">{{ $card['label'] }}</p>
            <p class="{{ $card['value_classes'] }}">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
