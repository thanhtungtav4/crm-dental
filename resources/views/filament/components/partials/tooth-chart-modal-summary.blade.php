@props(['chart'])

@if($chart['has_conditions'])
    <div class="mt-6">
        <h4 class="mb-3 font-medium text-gray-700 dark:text-gray-300">{{ $chart['summary_heading'] }}</h4>
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
            @foreach($chart['summary'] as $summaryItem)
                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-700">
                    <div class="text-sm font-medium {{ $summaryItem['condition_class'] }}">
                        {{ $summaryItem['name'] }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Răng: {{ $summaryItem['tooth_numbers'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
