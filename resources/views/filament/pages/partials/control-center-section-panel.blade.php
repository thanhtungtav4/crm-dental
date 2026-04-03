@props([
    'section',
])

<x-filament::section :heading="$section['title']" :description="$section['description']">
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($section['metrics'] as $metric)
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/60">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</p>
                            <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $metric['value'] }}</p>
                        </div>
                        <span class="{{ $metric['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                            {{ $metric['label'] }}
                        </span>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $metric['description'] }}</p>
                </div>
            @endforeach
        </div>

        @if(empty($section['rows']))
            <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ $section['empty_state_text'] }}
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($section['rows'] as $row)
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['subtitle'] }}</p>
                            </div>

                            <span class="{{ $row['badge_classes'] }} inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold">
                                {{ $row['badge'] }}
                            </span>
                        </div>

                        <dl class="mt-4 space-y-2">
                            @foreach($row['meta'] as $meta)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <dt class="text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</dt>
                                    <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $meta['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament::section>
