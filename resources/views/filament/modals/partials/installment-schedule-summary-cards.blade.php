@props(['summaryCards'])

<div class="mt-4 grid grid-cols-3 gap-4 rounded-lg bg-gray-50 p-4">
    @foreach($summaryCards as $summaryCard)
        <div>
            <div class="text-xs text-gray-600">{{ $summaryCard['label'] }}</div>
            <div class="{{ $summaryCard['value_classes'] }}">
                {{ $summaryCard['value'] }}
            </div>
        </div>
    @endforeach
</div>
