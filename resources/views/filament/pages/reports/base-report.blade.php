<x-filament::section>
    <div class="space-y-6">
        @php($stats = $this->getStats())
        @if(!empty($stats))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs font-medium text-gray-500">{{ $stat['label'] }}</div>
                        <div class="text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</div>
                        @if(!empty($stat['description']))
                            <div class="text-xs text-gray-500">{{ $stat['description'] }}</div>
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
