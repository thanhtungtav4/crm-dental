@props([
    'section',
])

<x-filament::section :heading="$section['title']" :description="$section['description']">
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($section['metrics'] as $metric)
                @include('filament.pages.partials.control-center-metric-card', [
                    'metric' => $metric,
                ])
            @endforeach
        </div>

        @if(empty($section['rows']))
            <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                {{ $section['empty_state_text'] }}
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                @foreach($section['rows'] as $row)
                    @include('filament.pages.partials.control-center-row-card', [
                        'row' => $row,
                    ])
                @endforeach
            </div>
        @endif
    </div>
</x-filament::section>
