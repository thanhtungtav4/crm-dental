<x-filament::section>
    <div class="space-y-6">
        @if(!empty($stats))
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/60">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
                        @if(!empty($stat['description']))
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament::section>
