@props(['legendItems'])

<div class="mb-6 flex justify-center gap-6 text-sm">
    @foreach($legendItems as $legendItem)
        <div class="flex items-center gap-2">
            <span class="{{ $legendItem['swatch_classes'] }}"></span>
            <span>{{ $legendItem['label'] }}</span>
        </div>
    @endforeach
</div>
