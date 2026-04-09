@props(['legendItems'])

<div class="note-teeth mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
    <div class="note-tooth-sign w-full text-gray-500 dark:text-gray-400">* chú thích hiện trạng răng</div>
    @foreach($legendItems as $legendItem)
        <div class="inline-flex items-center gap-1.5"><span class="{{ $legendItem['swatch_classes'] }}"></span> {{ $legendItem['label'] }}</div>
    @endforeach
</div>
