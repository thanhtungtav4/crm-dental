<x-filament::section>
    <div class="space-y-3">
        <div class="flex items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                    {{ $card['title'] }}
                </p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $card['value'] }}
                </p>
            </div>

            <span class="{{ $card['status_badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                {{ $card['status'] }}
            </span>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $card['description'] }}
        </p>

        @if(! empty($card['meta']))
            <div class="space-y-1">
                @foreach($card['meta'] as $meta)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $meta }}</p>
                @endforeach
            </div>
        @endif
    </div>
</x-filament::section>
