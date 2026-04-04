<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/60">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $panel['heading'] }}</h3>

    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
        @foreach($panel['items'] as $item)
            @if(is_array($item))
                <p><span class="font-medium {{ $item['tone_classes'] }}">{{ $item['label'] }}:</span> {{ $item['description'] }}</p>
            @else
                <p>{{ $item }}</p>
            @endif
        @endforeach
    </div>
</div>
