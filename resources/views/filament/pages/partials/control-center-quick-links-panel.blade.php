@props([
    'panel',
])

<x-filament::section :heading="$panel['heading']" :description="$panel['description']">
    <div class="{{ $panel['grid_classes'] }}">
        @foreach($panel['links'] as $link)
            <a
                href="{{ $link['url'] }}"
                class="rounded-2xl border border-gray-200 bg-white px-4 py-4 transition hover:border-primary-300 hover:bg-primary-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-gray-800 dark:bg-gray-950/60 dark:hover:border-primary-700 dark:hover:bg-primary-950/20 dark:focus-visible:ring-offset-gray-950"
            >
                <div class="space-y-2">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $link['label'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $link['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament::section>
