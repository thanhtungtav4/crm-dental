<x-filament-panels::page>
    <div class="space-y-6">
        @foreach($this->getSettingSections() as $section)
            <x-filament::section :heading="$section['title']" :description="$section['description']">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($section['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="block rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:bg-primary-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 dark:hover:bg-primary-900/20 dark:focus-visible:ring-offset-gray-950"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                                </div>
                                <span class="text-xs font-medium text-primary-600 dark:text-primary-400">Mở</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
